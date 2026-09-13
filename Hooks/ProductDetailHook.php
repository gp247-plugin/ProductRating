<?php
#App\GP247\Plugins\ProductRating\Hooks\ProductDetailHook.php

namespace App\GP247\Plugins\ProductRating\Hooks;

/**
 * Renders the review box into gp247/shop's product-detail extension point.
 *
 * Registered from Provider.php, so the plugin appears on the storefront with no
 * template edit at all: a site installs the plugin and the block is there.
 *
 * @aidlc-unit plugin-product-rating
 * @aidlc-story US-product-rating-storefront-hook
 * @aidlc-adr front_storefront-plugin-hooks
 */
class ProductDetailHook
{
    /**
     * Build the review block for one product page.
     *
     * @param array<string, mixed> $data Context from the screen; expects 'product'.
     * @return string HTML, or an empty string when reviews are off for this store
     *                or the screen did not hand over a product.
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-storefront-hook
     */
    public static function render(array $data = []): string
    {
        $product = $data['product'] ?? null;

        if ($product === null || empty($product->id)) {
            return '';
        }

        // Per-store on/off: the plugin can be installed site-wide but turned off
        // for this particular store.
        if (!function_exists('gp247_product_rating_enabled') || !gp247_product_rating_enabled()) {
            return '';
        }

        return view('Plugins/ProductRating::hooks.product_detail', [
            'productId' => (string) $product->id,
        ])->render();
    }
}
