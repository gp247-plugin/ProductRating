<?php
#App\GP247\Plugins\ProductRating\Services\ReviewService.php
namespace App\GP247\Plugins\ProductRating\Services;

use App\GP247\Plugins\ProductRating\Models\ProductReview;
use App\GP247\Plugins\ProductRating\Models\ProductReviewLog;
use Illuminate\Validation\ValidationException;

/**
 * Writing and moderating a product review.
 *
 * Moved out of the storefront review box (ReviewBox) and the admin queue
 * (ReviewManager) so the screens and any other caller — a seeding command, a
 * test — go through one implementation. The rules that make a review honest
 * live here, not in the caller: the right to review is granted per order, the
 * score stays on the store's scale, and a rejection needs a reason from the
 * closed list. Authorization (who may moderate, which store) stays with the
 * caller.
 *
 * @aidlc-unit plugin-product-rating
 * @aidlc-story US-product-rating-submit-review, US-product-rating-moderate-review
 */
final class ReviewService
{
    /**
     * May this customer review this product now, and which purchase would it be the review OF?
     *
     * Reviewing is granted PER ORDER: every qualifying purchase earns exactly
     * one review. `order_id` '' is the single slot of a customer with no
     * purchase behind them, which only exists while the purchase requirement is off.
     *
     * @param string      $productId
     * @param mixed       $customer  The signed-in customer, or null (guest).
     * @param string|null $storeId   Storefront store.
     * @return array{order_id: string|null, blocked: string|null} blocked = guest | already_reviewed | not_purchased.
     */
    public static function entitlement(string $productId, $customer, $storeId): array
    {
        if ($customer === null) {
            return ['order_id' => null, 'blocked' => 'guest'];
        }

        // WHY the seller's store, not the storefront's: on a shared-domain
        // marketplace the storefront is always the root store while an order of a
        // vendor's product belongs to that vendor's shop — filtering by the
        // storefront found no purchase at all (RISK-BIZ-pr-marketplace-review-blocked).
        // On a one-store site, or one store per domain, both are the same store.
        $orderStoreId = gp247_product_rating_seller_store_id($productId) ?? $storeId;

        // A purchase they have not spent yet wins, even when the requirement is
        // off: it carries the "verified purchase" badge, and spending the free
        // unverified slot instead would waste it.
        $reviewable = gp247_product_rating_reviewable_order($productId, $customer->id, $orderStoreId);
        if ($reviewable !== null) {
            return ['order_id' => $reviewable, 'blocked' => null];
        }

        if ((int) gp247_product_rating_config('require_purchased', $storeId) === 1) {
            // Distinguish "never bought it" from "bought it and already used
            // that purchase" — the wrong message reads as a lost order.
            $purchased = gp247_product_rating_purchased_order($productId, $customer->id, $orderStoreId);

            return [
                'order_id' => null,
                'blocked' => $purchased === null ? 'not_purchased' : 'already_reviewed',
            ];
        }

        // withTrashed: a review the customer withdrew is a TOMBSTONE that still
        // holds this slot — otherwise removing a review would become a way to
        // score the same product again and again.
        $usedFreeSlot = ProductReview::withTrashed()
            ->where('customer_id', $customer->id)
            ->where('product_id', $productId)
            ->where('order_id', '')
            ->exists();

        return $usedFreeSlot
            ? ['order_id' => null, 'blocked' => 'already_reviewed']
            : ['order_id' => '', 'blocked' => null];
    }

    /**
     * Write a customer's review (photos are the caller's: they arrive as uploads).
     *
     * @param string      $productId
     * @param mixed       $customer  Signed-in customer (required).
     * @param int         $rating    1 … the store's scale.
     * @param string      $content   Free text (markup stripped).
     * @param string|null $storeId   Storefront store.
     * @param string|null $ip        Client address, if known.
     * @return ProductReview
     * @throws ValidationException When the customer may not review, or the score is off the scale.
     */
    public static function submit(string $productId, $customer, int $rating, string $content, $storeId, ?string $ip = null): ProductReview
    {
        if ($customer === null) {
            // Login is a hard requirement, never a setting.
            throw ValidationException::withMessages(['rating' => trans('Plugins/ProductRating::lang.front.login_required')]);
        }

        $entitlement = self::entitlement($productId, $customer, $storeId);
        if ($entitlement['blocked'] !== null) {
            throw ValidationException::withMessages([
                'rating' => trans('Plugins/ProductRating::lang.front.'.(
                    $entitlement['blocked'] === 'not_purchased' ? 'purchase_required' : 'already_reviewed'
                )),
            ]);
        }
        if ($rating < 1 || $rating > gp247_product_rating_max($storeId)) {
            throw ValidationException::withMessages(['rating' => trans('validation.between.numeric', ['attribute' => 'rating', 'min' => 1, 'max' => gp247_product_rating_max($storeId)])]);
        }

        $autoApprove = (int) gp247_product_rating_config('auto_approve', $storeId) === 1;

        return ProductReview::create([
            'store_id' => $storeId,
            // The seller dimension a shop/vendor page groups by. On a shared-domain
            // marketplace store_id is ROOT for every vendor, so it cannot tell two
            // shops apart; the product's owner can.
            'seller_store_id' => gp247_product_rating_seller_store_id($productId) ?? $storeId,
            'product_id' => $productId,
            'customer_id' => $customer->id,
            // The purchase this review is the entitlement of — also what the
            // "verified purchase" badge reads.
            'order_id' => (string) $entitlement['order_id'],
            'customer_name' => $customer->name,
            'rating' => $rating,
            // gp247_clean strips scripting/markup: this text is rendered on a
            // public page next to other customers' content.
            'content' => gp247_clean($content),
            'status' => $autoApprove ? ProductReview::STATUS_APPROVED : ProductReview::STATUS_PENDING,
            'approved_at' => $autoApprove ? now() : null,
            'ip' => $ip,
        ]);
    }

    /**
     * Publish a review (clears a previous rejection) and log the decision.
     */
    public static function approve(ProductReview $review): void
    {
        $review->status = ProductReview::STATUS_APPROVED;
        $review->approved_at = now();
        // Clear a previous rejection so the row does not keep claiming a reason
        // for a decision that has since been reversed.
        $review->reject_reason = null;
        $review->reject_note = null;
        $review->save();

        ProductReviewLog::record((int) $review->id, ProductReviewLog::ACTION_APPROVE);
    }

    /**
     * Take a review off the storefront with a stated reason, and log the decision.
     *
     * A reason from the closed list is REQUIRED: "the score was low" must never
     * be able to masquerade as moderation (FTC 16 CFR 465.7). The row stays on
     * record so the order's review slot remains spent.
     *
     * @return bool False when the reason is not on the closed list (nothing written).
     */
    public static function reject(ProductReview $review, string $reason, string $note = ''): bool
    {
        if (!in_array($reason, ProductReview::REJECT_REASONS, true)) {
            return false;
        }
        $review->status = ProductReview::STATUS_REJECTED;
        $review->approved_at = null;
        $review->reject_reason = $reason;
        $review->reject_note = gp247_clean($note) ?: null;
        $review->save();

        ProductReviewLog::record((int) $review->id, ProductReviewLog::ACTION_REJECT, $review->reject_reason, $review->reject_note);

        return true;
    }
}
