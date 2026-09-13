<?php
#App\GP247\Plugins\ProductRating\Livewire\ReviewManager.php

namespace App\GP247\Plugins\ProductRating\Livewire;

use App\GP247\Plugins\ProductRating\Models\ProductReview;
use App\GP247\Plugins\ProductRating\Models\ProductReviewLog;
use GP247\Core\AdminShell\Domain\AdminUserContract;
use GP247\Core\AdminShell\Infrastructure\DataTableComponent;
use GP247\Core\AdminShell\Infrastructure\HasStoreScopeUi;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Models\ShopProductDescription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Moderation queue: every review, filterable by state and store, with approve /
 * reject / delete. A list screen rather than a two-panel ResourcePanel because
 * an admin never AUTHORS a review — they only rule on what customers wrote.
 *
 * Store scoping is a data rule, not a display one: a store-admin's queries are
 * constrained to their own store whether or not the store chrome is visible
 * (RISK-SEC-store-scope-ui-data-conflation).
 *
 * @aidlc-unit plugin-product-rating
 * @aidlc-adr ADR-001, ADR-005
 */
class ReviewManager extends DataTableComponent
{
    use HasStoreScopeUi;

    protected ?string $permission = null;

    /** @var string|null Screen title (plugin lang file, not the DB string table). */
    protected ?string $screenTitle = null;

    /** @var string Moderation state filter: '' = all, else a ProductReview::STATUS_* value. */
    public string $statusFilter = '';

    /** @var string Store filter for the root admin: '' = every store. */
    public string $storeFilter = '';

    /**
     * Seller-store filter: '' = every seller.
     *
     * WHY a second store filter: a review carries two stores — the storefront it
     * was written on ($storeFilter) and the store that SELLS the product. On a
     * marketplace the first is ROOT for every vendor, so it is the seller
     * dimension that answers "whose review is this".
     *
     * @var string
     */
    public string $sellerFilter = '';

    /** @var int|null Review the reject panel is open for; null = closed. */
    public ?int $rejectingId = null;

    /** @var string Chosen reason code for the rejection in progress. */
    public string $rejectReason = '';

    /** @var string Optional free-text detail for the rejection in progress. */
    public string $rejectNote = '';

    /** @var int|null Review the reply box is open for; null = closed. */
    public ?int $replyingId = null;

    /** @var string The public answer being written. */
    public string $replyContent = '';

    /** @var int Hard cap on a public reply (the model owns the rule). */
    protected const REPLY_MAX = ProductReview::REPLY_MAX;

    /**
     * @return void
     */
    public function mount(): void
    {
        $this->screenTitle = trans('Plugins/ProductRating::lang.admin.menu_review');

        parent::mount();
    }

    /**
     * @return ProductReview
     */
    protected function query()
    {
        return new ProductReview();
    }

    /**
     * Sortable columns; doubles as the sort whitelist.
     *
     * Product and store are deliberately absent: both are rendered from a
     * lookup, not from a column of this table, so sorting on them would need a
     * join that buys little for a queue an admin works through by date.
     *
     * @return array<string, string>
     */
    protected function columns(): array
    {
        return [
            'rating' => 'Rating',
            'status' => 'Status',
            'created_at' => 'Created at',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['content', 'customer_name'];
    }

    /**
     * Eager-load the photos so the "has N images" marker does not cost a query
     * per row.
     *
     * @return array<int, string>
     */
    protected function relations(): array
    {
        return ['images'];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function defaultSort(): array
    {
        // Oldest-pending-first is the wrong default for a queue that also shows
        // history; newest first matches how an admin checks "what came in".
        return ['created_at', 'desc'];
    }

    /**
     * Opt into store scoping so the picker/labels activate on a multi-store site.
     *
     * @return bool
     */
    protected function storeScopeOptIn(): bool
    {
        return true;
    }

    /**
     * Screen-level constraints applied to BOTH the listing and the delete path,
     * so a crafted row id cannot reach another store's review.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return void
     */
    protected function constrain($query): void
    {
        if ($this->storeScopeActive() && !$this->isRootScope()) {
            // Store-admin: hard-limited to their own store, regardless of any
            // filter value arriving from the client.
            $query->where('store_id', $this->storeContext());
        } elseif ($this->storeFilter !== '') {
            $query->where('store_id', $this->storeFilter);
        }

        if ($this->sellerFilter !== '') {
            $query->forSeller($this->sellerFilter);
        }

        if ($this->statusFilter !== '') {
            $query->where('status', (int) $this->statusFilter);
        }

        // Reviews a customer retracted stay visible to the admin as tombstones:
        // they are the record of a dispute, and hiding them would make the queue
        // disagree with the entitlement (the order's slot is still spent).
        $query->withTrashed();
    }

    /**
     * Attach the product name and its storefront URL to the current page's rows,
     * in TWO queries for the whole page.
     *
     * WHY not ShopProduct::getName() in the view: that runs a query per row
     * (N+1) because the name lives in the per-language description table. The
     * alias is fetched the same way so the moderator gets a link to the product
     * without the view touching the database.
     *
     * @return LengthAwarePaginator
     */
    protected function rows(): LengthAwarePaginator
    {
        $rows = parent::rows();

        $productIds = collect($rows->items())->pluck('product_id')->filter()->unique()->all();

        $names = $productIds === []
            ? collect()
            : ShopProductDescription::whereIn('product_id', $productIds)
                ->where('lang', gp247_get_locale())
                ->pluck('name', 'product_id');

        $aliases = $productIds === []
            ? collect()
            : ShopProduct::whereIn('id', $productIds)->pluck('alias', 'id');

        foreach ($rows as $row) {
            $row->product_name = $names[$row->product_id] ?? '';
            $row->product_url = $this->productUrl($aliases[$row->product_id] ?? null);
        }

        return $rows;
    }

    /**
     * Storefront URL of a product, or null when it cannot be built.
     *
     * Guarded: a review outlives the product it is about (someone may delete the
     * product later), and a missing alias must degrade to plain text rather than
     * throwing and taking the whole moderation screen down.
     *
     * @param string|null $alias
     * @return string|null
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-moderate-review
     */
    protected function productUrl(?string $alias): ?string
    {
        if ($alias === null || $alias === '') {
            return null;
        }

        try {
            return gp247_route_front('product.detail', ['alias' => $alias]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Reset to the first page when a filter changes, so the admin is not left on
     * a page number that no longer exists in the narrowed result set.
     *
     * @return void
     */
    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return void
     */
    public function updatedStoreFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return void
     */
    public function updatedSellerFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Publish a review.
     *
     * @param int $id
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-moderate-review
     */
    public function approve($id): void
    {
        $this->authorizeAction('approve');

        $review = $this->findInScope($id);
        if ($review === null) {
            return;
        }

        $review->status = ProductReview::STATUS_APPROVED;
        $review->approved_at = now();
        // Clear a previous rejection so the row does not keep claiming a reason
        // for a decision that has since been reversed.
        $review->reject_reason = null;
        $review->reject_note = null;
        $review->save();

        ProductReviewLog::record((int) $review->id, ProductReviewLog::ACTION_APPROVE);

        $this->notify('success', trans('Plugins/ProductRating::lang.admin.moderated'));
    }

    /**
     * Open the reject panel for one review.
     *
     * Rejecting is deliberately a two-step action: a reason must be chosen from
     * the closed list before the review comes down.
     *
     * @param int $id
     * @return void
     */
    public function startReject($id): void
    {
        $this->rejectingId = (int) $id;
        $this->rejectReason = '';
        $this->rejectNote = '';
        $this->replyingId = null;
    }

    /**
     * Close the reject panel without deciding.
     *
     * @return void
     */
    public function cancelReject(): void
    {
        $this->reset(['rejectingId', 'rejectReason', 'rejectNote']);
    }

    /**
     * Take a review off the storefront, with a stated reason.
     *
     * The review stays on record: the order's review slot remains spent, and the
     * reason is kept so the operator can audit the decision. A reason from the
     * closed list is REQUIRED — a rejection with no reason, or one invented by
     * the client, is refused, because "the score was low" must never be able to
     * masquerade as moderation (FTC 16 CFR 465.7).
     *
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-moderation-accountability
     */
    public function confirmReject(): void
    {
        $this->authorizeAction('reject');

        if (!in_array($this->rejectReason, ProductReview::REJECT_REASONS, true)) {
            $this->notify('error', trans('Plugins/ProductRating::lang.admin.reject_reason_required'));

            return;
        }

        $review = $this->findInScope($this->rejectingId);
        if ($review === null) {
            return;
        }

        $review->status = ProductReview::STATUS_REJECTED;
        $review->approved_at = null;
        $review->reject_reason = $this->rejectReason;
        $review->reject_note = gp247_clean($this->rejectNote) ?: null;
        $review->save();

        ProductReviewLog::record(
            (int) $review->id,
            ProductReviewLog::ACTION_REJECT,
            $review->reject_reason,
            $review->reject_note
        );

        $this->cancelReject();
        $this->notify('success', trans('Plugins/ProductRating::lang.admin.moderated'));
    }

    /**
     * Open the public-reply box for one review, pre-filled with any existing
     * answer (a shop gets ONE public answer, which it may rewrite).
     *
     * @param int $id
     * @return void
     */
    public function startReply($id): void
    {
        $review = $this->findInScope($id);
        if ($review === null) {
            return;
        }

        $this->replyingId = (int) $id;
        $this->replyContent = (string) $review->reply_content;
        $this->rejectingId = null;
    }

    /**
     * Close the reply box without saving.
     *
     * @return void
     */
    public function cancelReply(): void
    {
        $this->reset(['replyingId', 'replyContent']);
    }

    /**
     * Publish the shop's answer to a review.
     *
     * This is the tool a shop owner is meant to have instead of the power to
     * erase: a bad review is answered in public, not removed. Saving an empty
     * body withdraws the answer.
     *
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-shop-reply
     */
    public function saveReply(): void
    {
        $this->authorizeAction('reply');

        $review = $this->findInScope($this->replyingId);
        if ($review === null) {
            return;
        }

        // One write path for an answer, shared with the vendor surface
        // (ProductReview::publishReply): same cleaning, same cap, same log entry.
        // storeId stays null — this answer comes from the marketplace owner.
        $review->publishReply(
            $this->replyContent,
            function_exists('admin') && admin()->user() ? admin()->user()->id : null,
            null
        );

        $this->cancelReply();
        $this->notify('success', trans('Plugins/ProductRating::lang.admin.reply_saved'));
    }

    /**
     * Re-read a review through the same store constraints the listing uses, so
     * an id from outside this admin's scope resolves to nothing.
     *
     * @param int|null $id
     * @return ProductReview|null
     */
    protected function findInScope($id): ?ProductReview
    {
        if ($id === null) {
            return null;
        }

        $query = $this->query()->newQuery();
        $this->constrain($query);

        $review = $query->find($id);
        if ($review === null) {
            $this->notify('error', trans('Plugins/ProductRating::lang.admin.not_found'));
        }

        return $review;
    }

    /**
     * Whether the current admin may DESTROY a review.
     *
     * Only a system administrator may. A shop owner (a store-bound admin, or any
     * non-administrator staff account) can moderate what is published but must
     * never be able to erase what a customer wrote: a shop that can delete its
     * own bad reviews turns the whole rating into marketing, and the record is
     * also what an admin needs when a customer disputes a moderation decision.
     *
     * Public so the view can hide the affordance — but the rule is enforced in
     * delete()/bulkDelete() below, because the Livewire update endpoint is a
     * plain HTTP request and a hidden button is not a control.
     *
     * @return bool
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-delete-admin-only
     */
    public function canDeleteReviews(): bool
    {
        return app(AdminUserContract::class)->isAdministrator();
    }

    /**
     * Delete one review — system administrator only.
     *
     * @param int $id
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-delete-admin-only
     */
    public function delete($id): void
    {
        if (!$this->canDeleteReviews()) {
            $this->notify('error', trans('Plugins/ProductRating::lang.admin.delete_denied'));

            return;
        }

        parent::delete($id);
    }

    /**
     * Delete the selected reviews — system administrator only.
     *
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-delete-admin-only
     */
    public function bulkDelete(): void
    {
        if (!$this->canDeleteReviews()) {
            $this->notify('error', trans('Plugins/ProductRating::lang.admin.delete_denied'));

            return;
        }

        parent::bulkDelete();
    }

    /**
     * A system administrator's delete ERASES: the row, its photos, its image
     * rows and its audit trail.
     *
     * WHY override: the model soft-deletes by default (a customer's retraction
     * must leave a tombstone holding the order's review slot), so the inherited
     * path would only hide the rows an administrator asked to remove.
     *
     * @param array<int, mixed> $ids
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-delete-admin-only
     */
    protected function deleteRows(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $query = $this->query()->newQuery();
        $this->constrain($query);

        $query->whereIn('id', $ids)->get()->each(fn ($review) => $review->purge());
    }

    /**
     * @return string
     */
    protected function listView(): string
    {
        return 'Plugins/ProductRating::Admin.review_manager';
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        return [
            'statusLabels' => [
                ProductReview::STATUS_PENDING => trans('Plugins/ProductRating::lang.admin.status_pending'),
                ProductReview::STATUS_APPROVED => trans('Plugins/ProductRating::lang.admin.status_approved'),
                ProductReview::STATUS_REJECTED => trans('Plugins/ProductRating::lang.admin.status_rejected'),
            ],
            'statusColors' => [
                ProductReview::STATUS_PENDING => 'amber',
                ProductReview::STATUS_APPROVED => 'green',
                ProductReview::STATUS_REJECTED => 'gray',
            ],
            'sellerOptions' => $this->sellerOptions(),
            'rejectReasons' => collect(ProductReview::REJECT_REASONS)
                ->mapWithKeys(fn ($code) => [
                    $code => trans('Plugins/ProductRating::lang.admin.reject_reason_' . $code),
                ])
                ->all(),
        ];
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
        return GP247_ADMIN_PREFIX . '/productrating/review';
    }

    /**
     * Seller stores to choose from, keyed by id — the stores that actually have
     * reviews, so the filter never offers an option that returns nothing.
     *
     * @return array<string, string>
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-seller-reply-contract
     */
    public function sellerOptions(): array
    {
        $ids = $this->query()->newQuery()
            ->whereNotNull('seller_store_id')
            ->distinct()
            ->pluck('seller_store_id')
            ->filter()
            ->all();

        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach (\GP247\Core\Models\AdminStore::whereIn('id', $ids)->get() as $store) {
            $name = trim((string) $store->getTitle());
            $out[(string) $store->id] = $name !== '' ? $name : (string) $store->code;
        }

        return $out;
    }

    /**
     * Display name of the store that answered a review, for the queue's
     * "answered by" marker. Names are resolved once per screen.
     *
     * @param string|null $storeId
     * @return string|null
     */
    public function sellerName(?string $storeId): ?string
    {
        if (!$storeId) {
            return null;
        }

        return $this->sellerOptions()[$storeId] ?? $storeId;
    }
}
