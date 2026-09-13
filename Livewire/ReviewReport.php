<?php
#App\GP247\Plugins\ProductRating\Livewire\ReviewReport.php

namespace App\GP247\Plugins\ProductRating\Livewire;

use App\GP247\Plugins\ProductRating\Models\ProductReview;
use GP247\Core\AdminShell\Infrastructure\GP247AdminComponent;
use GP247\Core\AdminShell\Infrastructure\HasStoreScopeUi;
use GP247\Shop\Models\ShopProductDescription;

/**
 * Review statistics, broken down per store.
 *
 * The per-store table is the point of the screen: a root admin compares volume,
 * average score and moderation backlog across stores; a store-admin sees exactly
 * one row — their own store — because the same store constraint that guards the
 * moderation queue is applied here (data scoping is never gated on whether the
 * store chrome is visible, RISK-SEC-store-scope-ui-data-conflation).
 *
 * Every aggregate is computed in SQL (GROUP BY), not by loading rows into PHP —
 * a review table grows without bound and this screen must not grow with it.
 *
 * @aidlc-unit plugin-product-rating
 * @aidlc-adr ADR-001, ADR-005
 */
class ReviewReport extends GP247AdminComponent
{
    use HasStoreScopeUi;

    protected ?string $permission = null;

    /** @var int How many products to list in the "most reviewed" table. */
    protected const TOP_PRODUCT_LIMIT = 10;

    /**
     * @return void
     */
    public function mount(): void
    {
        parent::mount();
    }

    /**
     * Opt into store scoping so the store labels/chrome activate on a multi-store
     * site.
     *
     * @return bool
     */
    protected function storeScopeOptIn(): bool
    {
        return true;
    }

    /**
     * Apply the caller's store limit to any review query on this screen.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function constrain($query)
    {
        if ($this->storeScopeActive() && !$this->isRootScope()) {
            $query->where('store_id', $this->storeContext());
        }

        return $query;
    }

    /**
     * Site-wide (or store-wide) totals for the KPI tiles.
     *
     * @return array{total:int, approved:int, pending:int, average:float}
     */
    protected function totals(): array
    {
        $row = $this->constrain(ProductReview::query())
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved', [ProductReview::STATUS_APPROVED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending', [ProductReview::STATUS_PENDING])
            // Only approved reviews count towards the published average, matching
            // what a shopper actually sees on the storefront.
            ->selectRaw('AVG(CASE WHEN status = ? THEN rating END) as average', [ProductReview::STATUS_APPROVED])
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'approved' => (int) ($row->approved ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'average' => round((float) ($row->average ?? 0), 1),
        ];
    }

    /**
     * One row per SHOP: volume, published average and moderation backlog.
     *
     * Grouped by seller_store_id — the store that sells the product — not by the
     * storefront the review was written on. The two are the same store on a
     * single-store or plain multi-store site; on a shared-domain marketplace
     * every review is written on ROOT, so grouping by store_id would collapse
     * all vendors into one row and answer nothing.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function byStore()
    {
        return $this->constrain(ProductReview::query())
            // COALESCE covers rows written before the seller column existed and
            // not yet backfilled; they fall back to the storefront's store.
            ->selectRaw('COALESCE(seller_store_id, store_id) as store_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved', [ProductReview::STATUS_APPROVED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending', [ProductReview::STATUS_PENDING])
            ->selectRaw('AVG(CASE WHEN status = ? THEN rating END) as average', [ProductReview::STATUS_APPROVED])
            // Every selected non-aggregate column is grouped: MariaDB/MySQL in
            // ONLY_FULL_GROUP_BY mode rejects a query that leans on functional
            // dependency (see the money-audit 1055 regression).
            ->groupByRaw('COALESCE(seller_store_id, store_id)')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Distribution of published scores (how many 1-star, 2-star, …).
     *
     * @return array<int, int>
     */
    protected function distribution(): array
    {
        $rows = $this->constrain(ProductReview::query())
            ->where('status', ProductReview::STATUS_APPROVED)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->toArray();

        $out = [];
        for ($score = gp247_product_rating_max(); $score >= 1; $score--) {
            $out[$score] = (int) ($rows[$score] ?? 0);
        }

        return $out;
    }

    /**
     * The most-reviewed products, with their published average.
     *
     * @return array<int, array{product_id:string, name:string, total:int, average:float}>
     */
    protected function topProducts(): array
    {
        $rows = $this->constrain(ProductReview::query())
            ->where('status', ProductReview::STATUS_APPROVED)
            ->select('product_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('AVG(rating) as average')
            ->groupBy('product_id')
            ->orderByDesc('total')
            ->limit(self::TOP_PRODUCT_LIMIT)
            ->get();

        $names = $rows->isEmpty()
            ? collect()
            : ShopProductDescription::whereIn('product_id', $rows->pluck('product_id')->all())
                ->where('lang', gp247_get_locale())
                ->pluck('name', 'product_id');

        return $rows->map(fn ($row) => [
            'product_id' => (string) $row->product_id,
            'name' => (string) ($names[$row->product_id] ?? $row->product_id),
            'total' => (int) $row->total,
            'average' => round((float) $row->average, 1),
        ])->all();
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function render()
    {
        $totals = $this->totals();
        $distribution = $this->distribution();

        return view('Plugins/ProductRating::Admin.review_report', [
            'totals' => $totals,
            'byStore' => $this->byStore(),
            'distribution' => $distribution,
            // Precomputed so the view does not divide by zero while drawing bars.
            'distributionMax' => max(1, $distribution === [] ? 1 : max($distribution)),
            'topProducts' => $this->topProducts(),
            'ratingMax' => gp247_product_rating_max(),
        ])->layout('gp247-admin::layouts.admin', [
            'title' => trans('Plugins/ProductRating::lang.admin.menu_report'),
        ]);
    }

    /**
     * Canonical admin path this screen authorizes against (ADR-001 Layer-2).
     *
     * WHY a method and not the $screenUri property: that property is protected,
     * so Livewire does not carry it across update round-trips — it would be null
     * on every action after mount, and the path would fall back to the shared
     * /livewire/update endpoint instead of this screen. Computed here because
     * GP247_ADMIN_PREFIX is define()d at boot, not at class-compile time.
     *
     * @return string|null
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-adr ADR-001
     */
    protected function authScreenUri(): ?string
    {
        return GP247_ADMIN_PREFIX . '/productrating/report';
    }
}
