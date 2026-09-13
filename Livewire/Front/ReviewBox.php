<?php
#App\GP247\Plugins\ProductRating\Livewire\Front\ReviewBox.php

namespace App\GP247\Plugins\ProductRating\Livewire\Front;

use App\GP247\Plugins\ProductRating\Models\ProductReview;
use App\GP247\Plugins\ProductRating\Models\ProductReviewImage;
use App\GP247\Plugins\ProductRating\Models\ProductReviewLog;
use GP247\Front\Livewire\BaseFrontComponent;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;

/**
 * Storefront review box for one product: the published reviews, the rating
 * summary, and — for a signed-in customer who is allowed to — the review form.
 *
 * Extends BaseFrontComponent so a template developer can restyle the box by
 * dropping their own Blade file into the active template without touching PHP
 * (ADR-011).
 *
 * Every rule that decides whether someone may review is evaluated SERVER-SIDE in
 * submit(), never only in the view: the view merely hides what is not allowed.
 *
 * @aidlc-unit plugin-product-rating
 * @aidlc-story US-product-rating-submit-review
 * @aidlc-adr ADR-011
 */
class ReviewBox extends BaseFrontComponent
{
    use WithFileUploads;

    /** @var string ShopProduct id (uuid) this box belongs to. */
    public string $productId = '';

    /** @var int Score the customer picked; 0 = nothing picked yet. */
    public int $rating = 0;

    /** @var string Free-text review body (optional — the score is the mandatory part). */
    public string $content = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> Pending photo uploads. */
    public array $photos = [];

    /** @var int How many published reviews are listed; grows via showMore(). */
    public int $limit = 5;

    /** @var bool Whether this customer's review was just accepted (drives the thank-you state). */
    public bool $submitted = false;

    /** @var bool Whether the customer is editing the review they already wrote. */
    public bool $editing = false;

    /** @var string|null Feedback after the customer edited or removed their own review. */
    public ?string $ownNotice = null;

    /** @var int Page size for the "show more" button. */
    protected const PAGE_SIZE = 5;

    /** @var int Hard cap on the review body, matching the TEXT column's practical limit. */
    protected const CONTENT_MAX = 2000;

    /** @var int Per-photo size cap in kilobytes. */
    protected const PHOTO_MAX_KB = 2048;

    /**
     * Bind the box to its product.
     *
     * @param string $productId ShopProduct id (uuid).
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-submit-review
     */
    public function mount(string $productId): void
    {
        $this->productId = $productId;
    }

    /**
     * The store this box belongs to (the domain's store on a multi-store site,
     * ROOT on a single-store one). Every read and write is scoped to it so one
     * store's reviews never leak into another's product page.
     *
     * @return int|string
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-store-scope
     */
    protected function storeId()
    {
        return function_exists('gp247_plugin_store_id')
            ? gp247_plugin_store_id()
            : config('app.storeId');
    }

    /**
     * The signed-in customer, or null for a guest.
     *
     * @return \GP247\Shop\Models\ShopCustomer|null
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-login-required
     */
    protected function currentCustomer()
    {
        return function_exists('customer') ? customer()->user() : null;
    }

    /**
     * Why this visitor may not write a review right now, or null when they may.
     *
     * Returned as a reason CODE rather than a message so the view decides the
     * wording (and the call-to-action: a guest gets a login link, a customer who
     * has not bought the product gets an explanation).
     *
     * @return string|null One of: guest, already_reviewed, not_purchased.
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-submit-review
     */
    public function blockedReason(): ?string
    {
        return $this->entitlement()['blocked'];
    }

    /**
     * Decide whether this visitor may write a review right now, and which
     * purchase it would be the review OF.
     *
     * Reviewing is granted PER ORDER: every qualifying purchase earns exactly
     * one review, so buying twice earns two and a single purchase can never
     * become two scores. `order_id` '' is the one slot available to a customer
     * with no purchase behind them, which only exists while the purchase
     * requirement is off.
     *
     * Single source of truth for both the view (which message to show) and
     * submit() (which order to stamp) — so the form can never offer something
     * the write path would refuse, or vice versa.
     *
     * @return array{order_id: string|null, blocked: string|null}
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-per-order-entitlement
     */
    protected function entitlement(): array
    {
        $customer = $this->currentCustomer();
        if ($customer === null) {
            return ['order_id' => null, 'blocked' => 'guest'];
        }

        $storeId = $this->storeId();

        // A purchase they have not spent yet wins, even when the requirement is
        // off: it carries the "verified purchase" badge, and spending the free
        // unverified slot instead would waste it.
        $reviewable = gp247_product_rating_reviewable_order($this->productId, $customer->id, $storeId);
        if ($reviewable !== null) {
            return ['order_id' => $reviewable, 'blocked' => null];
        }

        if ((int) gp247_product_rating_config('require_purchased', $storeId) === 1) {
            // Distinguish "never bought it" from "bought it and already used
            // that purchase" — the wrong message reads as a lost order.
            $purchased = gp247_product_rating_purchased_order($this->productId, $customer->id, $storeId);

            return [
                'order_id' => null,
                'blocked' => $purchased === null ? 'not_purchased' : 'already_reviewed',
            ];
        }

        // withTrashed: a review the customer withdrew is a TOMBSTONE that still
        // holds this slot. Without it the check would report the slot free, the
        // customer would be offered the form, and the insert would then die on
        // the unique key — and, worse, removing a review would become a way to
        // score the same product again and again.
        $usedFreeSlot = ProductReview::withTrashed()
            ->where('customer_id', $customer->id)
            ->where('product_id', $this->productId)
            ->where('order_id', '')
            ->exists();

        return $usedFreeSlot
            ? ['order_id' => null, 'blocked' => 'already_reviewed']
            : ['order_id' => '', 'blocked' => null];
    }


    /**
     * The review this signed-in customer has already written for this product,
     * if any — including one still waiting for approval or refused, because it
     * is theirs and they must be able to see and change it.
     *
     * The newest is returned: with a per-order entitlement a customer may hold
     * several, and the one they just wrote is the one they mean to manage.
     *
     * @return ProductReview|null
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-customer-edit-delete
     */
    public function ownReview(): ?ProductReview
    {
        $customer = $this->currentCustomer();
        if ($customer === null) {
            return null;
        }

        return ProductReview::where('customer_id', $customer->id)
            ->where('product_id', $this->productId)
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Load the customer's own review into the form for editing.
     *
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-customer-edit-delete
     */
    public function editOwn(): void
    {
        $review = $this->ownReview();
        if ($review === null) {
            return;
        }

        $this->rating = (int) $review->rating;
        $this->content = (string) $review->content;
        $this->editing = true;
        $this->submitted = false;
        $this->ownNotice = null;
    }

    /**
     * Leave edit mode without saving.
     *
     * @return void
     */
    public function cancelEdit(): void
    {
        $this->reset(['rating', 'content', 'photos', 'editing']);
    }

    /**
     * Save the customer's changes to their own review.
     *
     * The edit goes back through moderation unless the store publishes
     * immediately: a review that was approved once must not become a different
     * text afterwards without anyone looking at it. Photos are left untouched —
     * changing them is a separate action the storefront does not offer yet.
     *
     * @return void
     * @throws ValidationException When the input is invalid.
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-customer-edit-delete
     */
    public function updateOwn(): void
    {
        $review = $this->ownReview();
        if ($review === null) {
            return;
        }

        $data = $this->validate($this->rules());

        $autoApprove = (int) gp247_product_rating_config('auto_approve', $this->storeId()) === 1;

        $review->rating = (int) $data['rating'];
        $review->content = gp247_clean((string) ($data['content'] ?? ''));
        $review->status = $autoApprove ? ProductReview::STATUS_APPROVED : ProductReview::STATUS_PENDING;
        $review->approved_at = $autoApprove ? now() : null;
        // A re-submitted review is a fresh decision, so a previous rejection
        // reason no longer describes it.
        $review->reject_reason = null;
        $review->reject_note = null;
        $review->save();

        ProductReviewLog::record(
            (int) $review->id,
            ProductReviewLog::ACTION_CUSTOMER_EDIT,
            null,
            null,
            (string) $review->customer_id
        );

        $this->reset(['rating', 'content', 'photos', 'editing']);
        $this->ownNotice = $autoApprove ? 'updated' : 'updated_pending';
    }

    /**
     * Take the customer's own review off the storefront at their request.
     *
     * The text and photos are deleted for good (that is what they asked for, and
     * what a data-erasure request requires), but the row survives as a tombstone
     * so the order's review slot stays spent — otherwise removing and rewriting
     * would be an unlimited way to re-roll a score.
     *
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-customer-edit-delete
     */
    public function removeOwn(): void
    {
        $review = $this->ownReview();
        if ($review === null) {
            return;
        }

        $reviewId = (int) $review->id;
        $customerId = (string) $review->customer_id;

        $review->retractByCustomer();

        ProductReviewLog::record(
            $reviewId,
            ProductReviewLog::ACTION_CUSTOMER_DELETE,
            null,
            null,
            $customerId
        );

        $this->reset(['rating', 'content', 'photos', 'editing', 'submitted']);
        $this->ownNotice = 'removed';
    }

    /**
     * Validation rules, built from the store's current settings.
     *
     * @return array<string, mixed>
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-submit-review
     */
    protected function rules(): array
    {
        $storeId = $this->storeId();
        $rules = [
            // The score is mandatory: a review with no score cannot feed the
            // average, which is the whole point of the feature.
            'rating' => 'required|integer|min:1|max:' . gp247_product_rating_max($storeId),
            'content' => 'nullable|string|max:' . self::CONTENT_MAX,
        ];

        if ($this->imagesAllowed()) {
            $rules['photos'] = 'array|max:' . $this->imageMax();
            $rules['photos.*'] = 'image|mimes:jpg,jpeg,png,webp|max:' . self::PHOTO_MAX_KB;
        }

        return $rules;
    }

    /**
     * Whether photo attachments are turned on for this store.
     *
     * @return bool
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-photo-toggle
     */
    public function imagesAllowed(): bool
    {
        return (int) gp247_product_rating_config('allow_image', $this->storeId()) === 1;
    }

    /**
     * Maximum photos per review, clamped so a bad DB value cannot turn one
     * submission into an unbounded upload.
     *
     * @return int
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-photo-toggle
     */
    public function imageMax(): int
    {
        $max = (int) gp247_product_rating_config('image_max', $this->storeId());

        return max(1, min(10, $max ?: 3));
    }

    /**
     * The rating scale in effect for this store.
     *
     * @return int
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-scale
     */
    public function ratingMax(): int
    {
        return gp247_product_rating_max($this->storeId());
    }

    /**
     * Persist the review.
     *
     * Re-checks every gate here rather than trusting the rendered state: the
     * Livewire update endpoint is a normal HTTP request a client can craft, so a
     * hidden form is not a control.
     *
     * @return void
     * @throws ValidationException When the input or the customer's eligibility fails.
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-submit-review
     */
    public function submit(): void
    {
        $customer = $this->currentCustomer();
        if ($customer === null) {
            // Login is a hard requirement, never a setting.
            throw ValidationException::withMessages([
                'rating' => trans('Plugins/ProductRating::lang.front.login_required'),
            ]);
        }

        $storeId = $this->storeId();

        // Re-resolved here rather than trusting anything the client sent: the
        // rendered state may be minutes old, and the update endpoint is a plain
        // HTTP request.
        $entitlement = $this->entitlement();

        if ($entitlement['blocked'] !== null) {
            throw ValidationException::withMessages([
                'rating' => trans('Plugins/ProductRating::lang.front.' . (
                    $entitlement['blocked'] === 'not_purchased' ? 'purchase_required' : 'already_reviewed'
                )),
            ]);
        }

        $orderId = (string) $entitlement['order_id'];

        $data = $this->validate($this->rules());

        $autoApprove = (int) gp247_product_rating_config('auto_approve', $storeId) === 1;

        $review = ProductReview::create([
            'store_id' => $storeId,
            // The seller dimension a shop/vendor page groups by. On a shared-domain
            // marketplace store_id is ROOT for every vendor, so it cannot tell two
            // shops apart; the product's owner can. Falls back to the storefront
            // store, which is the same value on a non-marketplace site.
            'seller_store_id' => gp247_product_rating_seller_store_id($this->productId) ?? $storeId,
            'product_id' => $this->productId,
            'customer_id' => $customer->id,
            // The purchase this review is the entitlement of; '' when the
            // customer had none (only possible while the requirement is off).
            // Also what the "verified purchase" badge reads, so the badge keeps
            // working if the setting changes later.
            'order_id' => $orderId,
            'customer_name' => $customer->name,
            'rating' => (int) $data['rating'],
            // gp247_clean strips scripting/markup: this text is rendered on a
            // public page next to other customers' content.
            'content' => gp247_clean((string) ($data['content'] ?? '')),
            'status' => $autoApprove ? ProductReview::STATUS_APPROVED : ProductReview::STATUS_PENDING,
            'approved_at' => $autoApprove ? now() : null,
            'ip' => request()->ip(),
        ]);

        if ($this->imagesAllowed()) {
            $this->storePhotos($review);
        }

        $this->reset(['rating', 'content', 'photos']);
        $this->submitted = true;
    }

    /**
     * Move the uploaded photos onto the shared public disk and record them.
     *
     * Files go to storage/app/public (the "gp247" disk) — never inside the
     * plugin folder, which 1-click update deletes and replaces.
     *
     * @param ProductReview $review
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-photo-toggle
     */
    protected function storePhotos(ProductReview $review): void
    {
        $directory = gp247_product_rating_image_dir();

        foreach (array_slice($this->photos, 0, $this->imageMax()) as $index => $photo) {
            $path = $photo->store($directory, 'gp247');

            if ($path === false) {
                continue;
            }

            ProductReviewImage::create([
                'review_id' => $review->id,
                'image' => $path,
                'sort' => $index,
            ]);
        }
    }

    /**
     * Show the next page of published reviews.
     *
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-list-reviews
     */
    public function showMore(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    /**
     * Template-relative view key, so a template can override the markup at
     * <Template>/livewire/productrating_review-box.blade.php (ADR-011).
     *
     * @return string
     */
    protected function templateViewKey(): string
    {
        return 'livewire.productrating_review-box';
    }

    /**
     * @return string
     */
    protected function defaultViewNamespace(): string
    {
        return 'Plugins/ProductRating';
    }

    /**
     * Data for the view: the published reviews of this product in this store,
     * plus the summary and the form's eligibility state.
     *
     * @return array<string, mixed>
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-list-reviews
     */
    protected function viewData(): array
    {
        $storeId = $this->storeId();

        $query = ProductReview::approved()
            ->where('product_id', $this->productId)
            ->where('store_id', $storeId);

        $total = (clone $query)->count();

        return [
            'reviews' => $query->with('images')
                ->orderBy('id', 'desc')
                ->limit($this->limit)
                ->get(),
            'reviewTotal' => $total,
            'hasMore' => $total > $this->limit,
            'summary' => gp247_product_rating_summary($this->productId, $storeId),
            'ratingMax' => $this->ratingMax(),
            'blockedReason' => $this->blockedReason(),
            'imagesAllowed' => $this->imagesAllowed(),
            'imageMax' => $this->imageMax(),
            'ownReview' => $this->ownReview(),
        ];
    }
}
