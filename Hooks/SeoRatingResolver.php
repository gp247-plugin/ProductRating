<?php
#App\GP247\Plugins\ProductRating\Hooks\SeoRatingResolver.php

namespace App\GP247\Plugins\ProductRating\Hooks;

/**
 * Gives gp247/front the rating of a product for its JSON-LD aggregateRating.
 *
 * Registered from Provider.php into `gp247-config.front.seo_product_rating_resolvers`
 * (runtime-append, same idiom as plugin_hooks); front calls it without knowing this
 * plugin exists, and simply ignores the key on a front version that predates it.
 *
 * @aidlc-unit plugin-product-rating
 * @aidlc-story US-product-rating-seo-aggregate-rating
 */
class SeoRatingResolver
{
    /**
     * Approved-review count and average of a product, in the store whose review box
     * the visitor sees — so search results show the same numbers as the page.
     *
     * @param string          $productId
     * @param int|string|null $storeId   Store front resolved; the plugin's own store
     *                                   resolution wins when available, as in ReviewBox.
     * @return array{ratingValue: float, reviewCount: int, bestRating: int}|null
     *         Null when reviews are off for the store or the product has none approved.
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-seo-aggregate-rating
     */
    public static function resolve(string $productId, $storeId = null): ?array
    {
        if ($productId === '' || !function_exists('gp247_product_rating_enabled')) {
            return null;
        }

        // WHY the plugin's own store: ReviewBox uses gp247_plugin_store_id() for the
        // numbers it displays; using the same id keeps the two in agreement.
        $storeId = function_exists('gp247_plugin_store_id') ? gp247_plugin_store_id() : ($storeId ?? config('app.storeId'));

        if (!gp247_product_rating_enabled($storeId)) {
            return null;
        }

        $summary = gp247_product_rating_summary($productId, $storeId);
        if ((int) ($summary['total'] ?? 0) < 1) {
            return null;
        }

        return [
            'ratingValue' => (float) $summary['average'],
            'reviewCount' => (int) $summary['total'],
            'bestRating' => gp247_product_rating_max($storeId),
        ];
    }
}
