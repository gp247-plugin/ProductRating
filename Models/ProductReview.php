<?php
#App\GP247\Plugins\ProductRating\Models\ProductReview.php

namespace App\GP247\Plugins\ProductRating\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * One customer review of one product, scoped to the store it was written on.
 *
 * Moderation states live in this model as constants rather than magic numbers so
 * the storefront filter, the admin screen and the report all agree on what
 * "published" means: only STATUS_APPROVED rows are ever public.
 */
class ProductReview extends Model
{
    /**
     * Soft deletes, because a removed review must still hold its order's review
     * slot: the entitlement is per order, so a hard delete would hand the
     * customer a fresh slot and let them re-roll the score. A system
     * administrator's delete uses forceDelete() to erase for real.
     */
    use SoftDeletes;

    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    /**
     * The closed list of reasons a review may be rejected.
     *
     * Closed on purpose: every reason here is about the CONTENT and applies
     * equally whatever score the review carries. "The score was low" is not on
     * the list and must never be — suppressing reviews by sentiment is exactly
     * what FTC 16 CFR 465.7 and EU Directive 2019/2161 prohibit.
     *
     * @var array<int, string>
     */
    public const REJECT_REASONS = [
        'spam',
        'abusive',
        'personal_data',
        'illegal',
        'conflict_of_interest',
        'off_topic',
    ];

    /** Hard cap on a public answer (shared by every surface that writes one). */
    public const REPLY_MAX = 1000;

    protected $table = GP247_DB_PREFIX . 'product_review';

    protected $fillable = [
        'store_id',
        'seller_store_id',
        'product_id',
        'customer_id',
        'order_id',
        'customer_name',
        'rating',
        'content',
        'status',
        'approved_at',
        'reject_reason',
        'reject_note',
        'reply_content',
        'reply_at',
        'reply_by',
        'reply_store_id',
        'ip',
    ];

    protected $casts = [
        'rating' => 'integer',
        'status' => 'integer',
        'approved_at' => 'datetime',
        'reply_at' => 'datetime',
        // reply_by is NOT cast: GP247 admin ids are strings ("AU-AAAAA"), and an
        // integer cast would turn one into 0.
    ];

    /**
     * Photos attached by the reviewer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function images()
    {
        return $this->hasMany(ProductReviewImage::class, 'review_id', 'id');
    }

    /**
     * The moderation trail of this review.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function logs()
    {
        return $this->hasMany(ProductReviewLog::class, 'review_id', 'id');
    }

    /**
     * Whether the shop has answered this review publicly.
     *
     * @return bool
     */
    public function hasReply(): bool
    {
        return trim((string) $this->reply_content) !== '';
    }

    /**
     * The reviewed product, for the admin list/report.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(\GP247\Shop\Models\ShopProduct::class, 'product_id', 'id');
    }

    /**
     * Limit a query to publicly visible reviews.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Limit a query to the reviews of one seller's products — the grouping a
     * vendor/shop page and the per-shop statistics use.
     *
     * Rows written before the seller column existed have it null; they are
     * matched by falling back to the storefront they were written on, which is
     * the same store on every non-marketplace site.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|string $sellerStoreId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForSeller($query, $sellerStoreId)
    {
        return $query->where(function ($where) use ($sellerStoreId) {
            $where->where('seller_store_id', $sellerStoreId)
                ->orWhere(function ($legacy) use ($sellerStoreId) {
                    $legacy->whereNull('seller_store_id')->where('store_id', $sellerStoreId);
                });
        });
    }

    /**
     * Whether this review has a purchase on record (drives the storefront
     * "verified purchase" badge). Independent of the require_purchased setting:
     * a review written while the setting was on stays marked even if the site
     * owner turns it off later.
     *
     * @return bool
     */
    public function isVerifiedPurchase(): bool
    {
        return !empty($this->order_id);
    }

    /**
     * Erase this review for good: its photo files, its image rows, its audit
     * trail and the row itself.
     *
     * WHY this is not the default delete(): the files live on the "gp247" disk,
     * outside the database, so nothing else would clean them up. Reserved for a
     * system administrator — a soft delete keeps the row as a tombstone that
     * holds the order's review slot (see the SoftDeletes note above).
     *
     * @return bool|null
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-delete-admin-only
     */
    public function purge()
    {
        foreach ($this->images as $image) {
            if (!empty($image->image)) {
                Storage::disk('gp247')->delete($image->image);
            }
            $image->delete();
        }

        $this->logs()->delete();

        return $this->forceDelete();
    }

    /**
     * Take this review off the storefront at the customer's request, keeping the
     * row as a tombstone.
     *
     * The visible content and the photos go (that is what the customer asked
     * for, and what a data-erasure request requires); the row stays so the
     * order's review slot remains spent.
     *
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-customer-edit-delete
     */
    public function retractByCustomer(): void
    {
        foreach ($this->images as $image) {
            if (!empty($image->image)) {
                Storage::disk('gp247')->delete($image->image);
            }
            $image->delete();
        }

        $this->content = null;
        $this->reply_content = null;
        $this->reply_at = null;
        $this->reply_by = null;
        $this->save();

        $this->delete();
    }

    /**
     * Publish (or withdraw) the single public answer to this review.
     *
     * The one write path for an answer, shared by the marketplace owner's
     * moderation queue and by a vendor answering through MultiVendor, so both
     * clean the body the same way, log the same event, and cannot drift apart.
     * An empty body withdraws the answer together with its attribution.
     *
     * It touches ONLY the four reply columns: no caller of this method can
     * approve, reject or delete, which is what lets a seller be given the
     * answering power without the moderation power.
     *
     * @param string      $body         Raw answer text (cleaned + truncated here).
     * @param string|null $userId       Who answered: admin_user id, or vendor_user id when $storeId is set.
     * @param string|null $storeId      The answering vendor store; null = the marketplace/shop owner.
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-seller-reply-contract
     */
    public function publishReply(string $body, ?string $userId = null, ?string $storeId = null): void
    {
        // gp247_clean strips scripting/markup: the answer is rendered on a public
        // page beside customer content.
        $body = trim(gp247_clean(mb_substr($body, 0, self::REPLY_MAX)));
        $has = $body !== '';

        $this->reply_content = $has ? $body : null;
        $this->reply_at = $has ? now() : null;
        $this->reply_by = $has ? $userId : null;
        $this->reply_store_id = $has ? $storeId : null;
        $this->save();

        ProductReviewLog::record((int) $this->id, ProductReviewLog::ACTION_REPLY, null, $body, null, $userId, $storeId);
    }
}
