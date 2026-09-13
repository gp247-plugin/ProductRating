<?php
#App\GP247\Plugins\ProductRating\Models\ProductReviewLog.php

namespace App\GP247\Plugins\ProductRating\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit trail of what was done to a review, and why.
 *
 * Exists because moderation has to be accountable: a rejection must be
 * attributable to a stated, listed reason rather than to the score it carried
 * (FTC 16 CFR 465.7 treats suppressing reviews by sentiment as unlawful unless
 * the criteria are applied equally to all reviews; EU Directive 2019/2161 bans
 * misrepresenting consumer reviews). A shop owner may reject — but the operator
 * can always see who rejected what, and on what grounds.
 *
 * @aidlc-unit plugin-product-rating
 * @aidlc-story US-product-rating-moderation-accountability
 */
class ProductReviewLog extends Model
{
    public const ACTION_APPROVE = 'approve';
    public const ACTION_REJECT = 'reject';
    public const ACTION_REPLY = 'reply';
    public const ACTION_DELETE = 'delete';
    public const ACTION_CUSTOMER_EDIT = 'customer_edit';
    public const ACTION_CUSTOMER_DELETE = 'customer_delete';

    protected $table = GP247_DB_PREFIX . 'product_review_log';

    /**
     * Only a creation timestamp: the trail is append-only, so an "updated at"
     * would be a column that must never change.
     *
     * @var bool
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'review_id',
        'action',
        'reason_code',
        'note',
        'admin_id',
        'customer_id',
    ];

    protected $casts = [
        'review_id' => 'integer',
        // admin_id / customer_id are NOT cast: every GP247 id is a string
        // ("AU-…", "CUS-…") and an integer cast would flatten one to 0.
        'created_at' => 'datetime',
    ];

    /**
     * Record one moderation event.
     *
     * The actor is resolved here rather than at each call site so no code path
     * can write an unattributed entry.
     *
     * @param int         $reviewId
     * @param string      $action     One of the ACTION_* constants.
     * @param string|null $reasonCode Reason code for a rejection.
     * @param string|null $note       Free-text detail (optional).
     * @param string|null $customerId Set when the customer acted on their own review.
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-moderation-accountability
     */
    public static function record(
        int $reviewId,
        string $action,
        ?string $reasonCode = null,
        ?string $note = null,
        ?string $customerId = null
    ): void {
        $adminId = null;
        if ($customerId === null && function_exists('admin') && admin()->user()) {
            $adminId = admin()->user()->id;
        }

        self::create([
            'review_id' => $reviewId,
            'action' => $action,
            'reason_code' => $reasonCode,
            'note' => $note !== null ? mb_substr($note, 0, 255) : null,
            'admin_id' => $adminId,
            'customer_id' => $customerId,
        ]);
    }
}
