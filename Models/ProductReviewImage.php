<?php
#App\GP247\Plugins\ProductRating\Models\ProductReviewImage.php

namespace App\GP247\Plugins\ProductRating\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One photo attached to a review.
 *
 * `image` is a path on the shared "gp247" disk (storage/app/public), never a
 * path inside the plugin folder — 1-click update replaces that folder wholesale
 * and would delete the customers' photos with it (plugin standard #7).
 */
class ProductReviewImage extends Model
{
    protected $table = GP247_DB_PREFIX . 'product_review_image';

    protected $fillable = [
        'review_id',
        'image',
        'sort',
    ];

    protected $casts = [
        'review_id' => 'integer',
        'sort' => 'integer',
    ];

    /**
     * The review this photo belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function review()
    {
        return $this->belongsTo(ProductReview::class, 'review_id', 'id');
    }

    /**
     * Public URL of the photo for the storefront/admin thumbnail.
     *
     * @return string
     */
    public function getUrl(): string
    {
        return \Illuminate\Support\Facades\Storage::disk('gp247')->url($this->image);
    }
}
