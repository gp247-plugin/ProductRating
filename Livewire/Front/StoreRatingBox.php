<?php
#App\GP247\Plugins\ProductRating\Livewire\Front\StoreRatingBox.php

namespace App\GP247\Plugins\ProductRating\Livewire\Front;

use App\GP247\Plugins\ProductRating\Models\ProductReview;
use GP247\Front\Livewire\BaseFrontComponent;
use GP247\Shop\Models\ShopProductDescription;

/**
 * A shop's reputation panel: total reviews, average score, the per-score
 * breakdown, and the reviews themselves — each showing which product it is
 * about — filterable by score.
 *
 * This is the ready-made surface for a shop/vendor storefront page (the
 * "gian hàng" link on a marketplace). A multi-vendor plugin mounts it with the
 * vendor's store id and gets the whole panel:
 *
 *     @livewire('gp247-productrating-front::store-rating-box', ['sellerStoreId' => $store->id])
 *
 * A plugin that wants its own markup instead can skip this component and call
 * the helpers directly (gp247_product_rating_store_summary /
 * gp247_product_rating_store_products / gp247_product_rating_bulk_summary).
 *
 * READ-ONLY on purpose: a review is always written from the product page, where
 * the purchase and one-per-product rules are evaluated (see ReviewBox).
 *
 * @aidlc-unit plugin-product-rating
 * @aidlc-story US-product-rating-shop-reputation
 * @aidlc-adr ADR-011
 */
class StoreRatingBox extends BaseFrontComponent
{
    /** @var string The store whose products are being reviewed (the vendor/shop). */
    public string $sellerStoreId = '';

    /** @var string Score filter: '' = every score, else 1..rating_max. */
    public string $scoreFilter = '';

    /** @var int How many reviews are listed; grows via showMore(). */
    public int $limit = 10;

    /** @var int How many reviewed products are listed in the breakdown. */
    public int $productLimit = 10;

    /** @var int Page size for the "show more" button. */
    protected const PAGE_SIZE = 10;

    /**
     * @param int|string $sellerStoreId The vendor/shop store id.
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-shop-reputation
     */
    public function mount($sellerStoreId): void
    {
        $this->sellerStoreId = (string) $sellerStoreId;
    }

    /**
     * Narrow the list to one score, and go back to the first page so the viewer
     * is not left looking at an empty tail.
     *
     * @param int|string $score Score to filter by; '' clears the filter.
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-shop-reputation
     */
    public function filterScore($score = ''): void
    {
        $this->scoreFilter = (string) $score;
        $this->limit = self::PAGE_SIZE;
    }

    /**
     * Show the next page of reviews.
     *
     * @return void
     */
    public function showMore(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    /**
     * The rating scale to draw. Read for the shop's own store so a marketplace
     * where one vendor is on a 10-point scale still renders correctly.
     *
     * @return int
     */
    public function ratingMax(): int
    {
        return gp247_product_rating_max($this->sellerStoreId);
    }

    /**
     * @return string
     */
    protected function templateViewKey(): string
    {
        return 'livewire.productrating_store-rating-box';
    }

    /**
     * @return string
     */
    protected function defaultViewNamespace(): string
    {
        return 'Plugins/ProductRating';
    }

    /**
     * @return array<string, mixed>
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-shop-reputation
     */
    protected function viewData(): array
    {
        $query = ProductReview::approved()
            ->forSeller($this->sellerStoreId)
            ->when($this->scoreFilter !== '', fn ($q) => $q->where('rating', (int) $this->scoreFilter));

        $total = (clone $query)->count();

        $reviews = $query->with('images')
            ->orderBy('id', 'desc')
            ->limit($this->limit)
            ->get();

        // One lookup for the whole page: each review shows which product it is
        // about, and the name lives in the per-language description table.
        $names = $reviews->isEmpty()
            ? collect()
            : ShopProductDescription::whereIn('product_id', $reviews->pluck('product_id')->unique()->all())
                ->where('lang', gp247_get_locale())
                ->pluck('name', 'product_id');

        foreach ($reviews as $review) {
            $review->product_name = $names[$review->product_id] ?? '';
        }

        return [
            'summary' => gp247_product_rating_store_summary($this->sellerStoreId),
            'products' => gp247_product_rating_store_products($this->sellerStoreId, $this->productLimit),
            'reviews' => $reviews,
            'filteredTotal' => $total,
            'hasMore' => $total > $this->limit,
            'ratingMax' => $this->ratingMax(),
        ];
    }
}
