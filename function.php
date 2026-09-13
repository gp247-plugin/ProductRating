<?php
/**
 * Plugin helper functions (loaded by Provider.php when the plugin is active).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * Update-safe user configuration (plugin standard #7 — ADR
 * plugin-manager_extension-update-flow, RISK-OPS-plugin-config-file-overwrite).
 *
 * 1-click update OVERWRITES every file of the plugin but PRESERVES admin_config
 * (DB) and the plugin's own tables. So config.php holds DEFAULTS only and the
 * site owner's values live in admin_config, written per store by the settings
 * screen (Livewire\AdminLivewire extends the core ConfigForm).
 *
 * gp247_product_rating_config() is the single read path: per-store row → GLOBAL
 * row → config.php default. Every caller (front component, admin screens,
 * report) goes through it so the precedence rule exists in exactly one place.
 * ─────────────────────────────────────────────────────────────────────────────
 */

use App\GP247\Plugins\ProductRating\Models\ProductReview;

if (!function_exists('gp247_product_rating_config')) {
    /**
     * Read one effective plugin setting for a store.
     *
     * Keys are stored in admin_config prefixed with "productrating_" (the
     * admin_config key column is a FLAT namespace shared by every plugin, so an
     * unprefixed key such as "rating_max" would collide with another package).
     * The prefix is an implementation detail of this helper — callers pass the
     * short name used in config.php.
     *
     * @param string          $key     Short setting name, e.g. "rating_max".
     * @param int|string|null $storeId Store to read for; null = the current context store.
     * @return mixed The store's value, else the GLOBAL value, else the config.php default.
     */
    function gp247_product_rating_config(string $key, $storeId = null)
    {
        $storeId = $storeId ?? (function_exists('gp247_plugin_store_id')
            ? gp247_plugin_store_id()
            : config('app.storeId'));

        $default = config('Plugins/ProductRating.' . $key);

        return gp247_config('productrating_' . $key, $storeId, $default);
    }
}

if (!function_exists('gp247_product_rating_max')) {
    /**
     * The rating scale in effect, clamped to a sane range.
     *
     * WHY clamp: the value reaches here from admin_config, which an operator can
     * also edit directly in the DB. Validation rules and the star widget are
     * both built from this number, so a 0 would make the form unsubmittable and
     * a huge value would render thousands of stars.
     *
     * @param int|string|null $storeId
     * @return int A value between 1 and 10.
     */
    function gp247_product_rating_max($storeId = null): int
    {
        $max = (int) gp247_product_rating_config('rating_max', $storeId);

        return max(1, min(10, $max ?: 5));
    }
}

if (!function_exists('gp247_product_rating_enabled')) {
    /**
     * Whether reviews are active for this store: the plugin is installed and
     * globally on (gp247_extension_check_active) AND not turned off for this
     * store (the per-store enable override written by the ConfigForm toggle).
     *
     * @param int|string|null $storeId
     * @return bool
     */
    function gp247_product_rating_enabled($storeId = null): bool
    {
        if (!gp247_extension_check_active('Plugins', 'ProductRating')) {
            return false;
        }

        $storeId = $storeId ?? (function_exists('gp247_plugin_store_id')
            ? gp247_plugin_store_id()
            : config('app.storeId'));

        return !function_exists('gp247_plugin_store_enabled')
            || gp247_plugin_store_enabled('ProductRating', $storeId);
    }
}

if (!function_exists('gp247_product_rating_qualifying_orders')) {
    /**
     * Query of the customer's orders that contain this product and count as a
     * purchase.
     *
     * Cancelled/failed orders do NOT count — otherwise placing an order and
     * immediately cancelling it would be a free pass to review.
     *
     * @param string          $productId  ShopProduct id (uuid).
     * @param string          $customerId ShopCustomer id (uuid).
     * @param int|string|null $storeId    Limit to one store; null = any store.
     * @return \Illuminate\Database\Eloquent\Builder Order-detail rows of qualifying orders.
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-per-order-entitlement
     */
    function gp247_product_rating_qualifying_orders(string $productId, string $customerId, $storeId = null)
    {
        $excluded = [
            \GP247\Shop\Models\ShopOrderStatus::CANCELED,
            \GP247\Shop\Models\ShopOrderStatus::FAILED,
        ];

        return \GP247\Shop\Models\ShopOrderDetail::query()
            ->where('product_id', $productId)
            ->whereIn('order_id', function ($query) use ($customerId, $storeId, $excluded) {
                $query->select('id')
                    ->from(GP247_DB_PREFIX . 'shop_order')
                    ->where('customer_id', $customerId)
                    ->whereNotIn('status', $excluded)
                    ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId));
            });
    }
}

if (!function_exists('gp247_product_rating_purchased_order')) {
    /**
     * Find ANY order of this customer that proves they bought this product,
     * whether or not it has already been reviewed.
     *
     * Used to tell two refusals apart: a customer who never bought the product
     * ("not_purchased") and one who bought it and already used that purchase
     * ("already_reviewed"). Showing the wrong one reads as the shop losing
     * their order.
     *
     * @param string          $productId  ShopProduct id (uuid).
     * @param string          $customerId ShopCustomer id (uuid).
     * @param int|string|null $storeId    Limit to one store; null = any store.
     * @return string|null The order id, or null when no qualifying order exists.
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-require-purchase
     */
    function gp247_product_rating_purchased_order(string $productId, string $customerId, $storeId = null): ?string
    {
        if ($productId === '' || $customerId === '') {
            return null;
        }

        $orderId = gp247_product_rating_qualifying_orders($productId, $customerId, $storeId)
            ->orderBy('id', 'desc')
            ->value('order_id');

        return $orderId ? (string) $orderId : null;
    }
}

if (!function_exists('gp247_product_rating_reviewable_order')) {
    /**
     * Find the purchase this customer may still review: a qualifying order for
     * this product that they have NOT already reviewed.
     *
     * This is the entitlement rule. Reviewing is granted PER ORDER, not per
     * product (industry standard): buying twice earns two reviews, each purchase
     * being a separate experience, while a single purchase can never be turned
     * into two scores. Because the slot is consumed by the order rather than by
     * the product, deleting a review does not hand back a free re-roll.
     *
     * @param string          $productId  ShopProduct id (uuid).
     * @param string          $customerId ShopCustomer id (uuid).
     * @param int|string|null $storeId    Limit to one store; null = any store.
     * @return string|null The order id still available to review, or null.
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-per-order-entitlement
     */
    function gp247_product_rating_reviewable_order(string $productId, string $customerId, $storeId = null): ?string
    {
        if ($productId === '' || $customerId === '') {
            return null;
        }

        $orderId = gp247_product_rating_qualifying_orders($productId, $customerId, $storeId)
            // Already-spent purchases. Rejected reviews count as spent too: a
            // refused review must not be a way to try again until one sticks.
            ->whereNotIn('order_id', function ($query) use ($productId, $customerId) {
                $query->select('order_id')
                    ->from(GP247_DB_PREFIX . 'product_review')
                    ->where('customer_id', $customerId)
                    ->where('product_id', $productId);
            })
            ->orderBy('id', 'desc')
            ->value('order_id');

        return $orderId ? (string) $orderId : null;
    }
}

if (!function_exists('gp247_product_rating_summary')) {
    /**
     * Aggregate of the APPROVED reviews of a product: count, average, and the
     * per-score distribution used by the storefront bar chart.
     *
     * Only approved rows are counted — a pending or rejected review must never
     * move the public average.
     *
     * @param string          $productId
     * @param int|string|null $storeId Limit to one store; null = any store.
     * @return array{total:int, average:float, distribution:array<int,int>}
     */
    function gp247_product_rating_summary(string $productId, $storeId = null): array
    {
        $rows = ProductReview::query()
            ->approved()
            ->where('product_id', $productId)
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->toArray();

        $total = 0;
        $sum = 0;
        $distribution = [];
        foreach ($rows as $rating => $count) {
            $rating = (int) $rating;
            $count = (int) $count;
            $distribution[$rating] = $count;
            $total += $count;
            $sum += $rating * $count;
        }

        return [
            'total' => $total,
            'average' => $total > 0 ? round($sum / $total, 1) : 0.0,
            'distribution' => $distribution,
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Seller / shop integration seam
|--------------------------------------------------------------------------
| The three helpers below are the PUBLIC contract another extension (e.g. a
| multi-vendor plugin building a shop storefront page) calls to render a shop's
| reputation — total reviews, average score, the per-score breakdown and which
| products the reviews belong to.
|
| They group by seller_store_id (the store that SELLS the product), not by the
| storefront the review was written on: on a shared-domain marketplace every
| review is written on ROOT, so store_id cannot tell two vendors apart. On a
| single-store or plain multi-store site the two columns hold the same value and
| these helpers behave identically to the per-store ones.
|
| All of them count APPROVED reviews only — a pending or rejected review must
| never contribute to a shop's public reputation.
*/

if (!function_exists('gp247_product_rating_store_summary')) {
    /**
     * A shop's overall reputation: how many published reviews it has, the
     * average score, and how those scores are distributed.
     *
     * @param int|string $sellerStoreId The store that sells the products.
     * @return array{total:int, average:float, distribution:array<int,int>}
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-shop-reputation
     */
    function gp247_product_rating_store_summary($sellerStoreId): array
    {
        $rows = ProductReview::approved()
            ->forSeller($sellerStoreId)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->toArray();

        $total = 0;
        $sum = 0;
        $distribution = [];
        foreach ($rows as $rating => $count) {
            $rating = (int) $rating;
            $count = (int) $count;
            $distribution[$rating] = $count;
            $total += $count;
            $sum += $rating * $count;
        }

        return [
            'total' => $total,
            'average' => $total > 0 ? round($sum / $total, 1) : 0.0,
            'distribution' => $distribution,
        ];
    }
}

if (!function_exists('gp247_product_rating_store_products')) {
    /**
     * The shop's reviewed products, each with its own count and average — the
     * "which product is this rating about" breakdown a shop page shows.
     *
     * Product names are resolved in ONE extra query for the whole page, so a
     * caller can render the list without an N+1.
     *
     * @param int|string $sellerStoreId The store that sells the products.
     * @param int        $limit         Page size (clamped to 1..100).
     * @param int        $offset        Rows to skip.
     * @return array<int, array{product_id:string, name:string, total:int, average:float}>
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-shop-reputation
     */
    function gp247_product_rating_store_products($sellerStoreId, int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));

        $rows = ProductReview::approved()
            ->forSeller($sellerStoreId)
            ->select('product_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('AVG(rating) as average')
            ->groupBy('product_id')
            ->orderByDesc('total')
            ->offset(max(0, $offset))
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = \GP247\Shop\Models\ShopProductDescription::whereIn('product_id', $rows->pluck('product_id')->all())
            ->where('lang', gp247_get_locale())
            ->pluck('name', 'product_id');

        return $rows->map(fn ($row) => [
            'product_id' => (string) $row->product_id,
            'name' => (string) ($names[$row->product_id] ?? $row->product_id),
            'total' => (int) $row->total,
            'average' => round((float) $row->average, 1),
        ])->all();
    }
}

if (!function_exists('gp247_product_rating_bulk_summary')) {
    /**
     * Count and average for many products at once, for a product grid that wants
     * a star line on every card without one query per card.
     *
     * @param array<int, string> $productIds
     * @param int|string|null    $sellerStoreId Limit to one shop; null = every shop.
     * @return array<string, array{total:int, average:float}> Keyed by product id; products with no published review are absent.
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-shop-reputation
     */
    function gp247_product_rating_bulk_summary(array $productIds, $sellerStoreId = null): array
    {
        $productIds = array_values(array_unique(array_filter($productIds)));
        if ($productIds === []) {
            return [];
        }

        $rows = ProductReview::approved()
            ->whereIn('product_id', $productIds)
            ->when($sellerStoreId !== null, fn ($query) => $query->forSeller($sellerStoreId))
            ->select('product_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('AVG(rating) as average')
            ->groupBy('product_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->product_id] = [
                'total' => (int) $row->total,
                'average' => round((float) $row->average, 1),
            ];
        }

        return $out;
    }
}

if (!function_exists('gp247_product_rating_seller_store_id')) {
    /**
     * The store that sells a product — the value stamped on a new review as its
     * seller dimension.
     *
     * @param string $productId
     * @return int|string|null The owning store, or null when the product is gone.
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-seller-dimension
     */
    function gp247_product_rating_seller_store_id(string $productId)
    {
        if ($productId === '') {
            return null;
        }

        return \GP247\Shop\Models\ShopProduct::where('id', $productId)->value('store_id');
    }
}

if (!function_exists('gp247_product_rating_image_dir')) {
    /**
     * Directory (on the shared "gp247" disk = storage/app/public) where reviewer
     * photos are written.
     *
     * MUST stay outside app/GP247/Plugins/ProductRating: 1-click update deletes
     * and replaces the whole plugin folder, so anything stored there is lost
     * (plugin standard #7). Bucketed per month to keep directories small.
     *
     * @return string Relative path on the disk, e.g. "product_review/2026/09".
     */
    function gp247_product_rating_image_dir(): string
    {
        return 'product_review/' . date('Y') . '/' . date('m');
    }
}

/*
 |------------------------------------------------------------------------------
 | Seller-side contract (S5-1)
 |------------------------------------------------------------------------------
 | A marketplace vendor answers the reviews of the products their own store
 | sells. That surface lives in another plugin (MultiVendor), behind another auth
 | guard (`vendor`) and another shell, so it cannot be a screen of this plugin —
 | but it must not import this plugin's classes either, or a site without
 | ProductRating would fatal on a class that is not there.
 |
 | So the capability is published as functions: a consumer checks
 | function_exists() and degrades to "no reviews screen". The WRITE function is
 | also the fence — it only ever touches the four reply columns of a review that
 | belongs to the given seller store, so a vendor surface has no path to approve,
 | reject or delete, by construction rather than by convention.
 */

if (!function_exists('gp247_product_rating_review_model')) {
    /**
     * A fresh review model, for a consumer that needs to build a listing query
     * (a Livewire data table) without naming this plugin's classes.
     *
     * @return \App\GP247\Plugins\ProductRating\Models\ProductReview
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-seller-reply-contract
     */
    function gp247_product_rating_review_model()
    {
        return new ProductReview();
    }
}

if (!function_exists('gp247_product_rating_seller_constrain')) {
    /**
     * Narrow a review query to the reviews of ONE seller store — the marketplace
     * dimension. Applies the same semantics as the model's forSeller scope,
     * including rows written before the seller column existed.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|string $sellerStoreId
     * @return \Illuminate\Database\Eloquent\Builder
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-seller-reply-contract
     */
    function gp247_product_rating_seller_constrain($query, $sellerStoreId)
    {
        return $query->forSeller($sellerStoreId);
    }
}

if (!function_exists('gp247_product_rating_seller_reply')) {
    /**
     * Publish (or withdraw, with an empty body) a seller's public answer.
     *
     * The ONLY write this plugin opens to a seller. It re-reads the review
     * through the seller filter, so an id crafted by the client resolves to
     * nothing instead of reaching another store's review, and it delegates to
     * ProductReview::publishReply(), which cannot change moderation state.
     *
     * @param int        $reviewId
     * @param int|string $sellerStoreId The store answering (it must own the review).
     * @param string     $body          Answer text; empty withdraws the answer.
     * @param string|null $userId       The vendor user answering, for the audit trail.
     * @return bool True when the answer was written, false when the review is not this seller's.
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-seller-reply-contract
     */
    function gp247_product_rating_seller_reply(int $reviewId, $sellerStoreId, string $body, ?string $userId = null): bool
    {
        $review = ProductReview::withTrashed()->forSeller($sellerStoreId)->find($reviewId);
        if ($review === null) {
            return false;
        }

        $review->publishReply($body, $userId, (string) $sellerStoreId);

        return true;
    }
}

