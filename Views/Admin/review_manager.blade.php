{{--
    Product review moderation queue: filter by state (and by store for the root
    admin), then approve / reject / delete.

    Only classes that already exist in the core admin bundle (or its safelist)
    are used — the TailAdmin CSS is compiled statically from core's own Blade and
    never scans plugin views, so a brand-new utility combination would have no
    rule behind it (.claude/rules/gp247.md §3a).

    Variables: $rows (paginator, each row carries ->product_name),
               $statusLabels, $statusColors.
--}}
<div>
    {{-- selected-count drives the bulk-delete button, so it is reported as 0 for
         anyone who may not delete: a shop owner moderates, only a system admin
         erases (the rule itself is enforced in ReviewManager::bulkDelete()). --}}
    <x-gp247::list-toolbar :placeholder="gp247_language_render('Plugins/ProductRating::lang.admin.search_placeholder')"
        :selected-count="$this->canDeleteReviews() ? count($selected) : 0"
        :bulk-confirm="gp247_language_render('action.delete_confirm')">
        <x-slot:filters>
            <select wire:model.live="statusFilter"
                class="rounded-lg border border-gray-300 px-2 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                <option value="">{{ gp247_language_render('Plugins/ProductRating::lang.admin.status_all') }}</option>
                @foreach ($statusLabels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            {{-- Store filter: root admin only. A store-admin is already hard-limited
                 to their own store in the query, so the control would be a no-op. --}}
            @if ($this->storeScopeUiVisible())
                <select wire:model.live="storeFilter"
                    class="rounded-lg border border-gray-300 px-2 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    <option value="">{{ gp247_language_render('Plugins/ProductRating::lang.admin.store_all') }}</option>
                    @foreach ($this->storeOptions() as $storeId => $storeName)
                        <option value="{{ $storeId }}">{{ $storeName }}</option>
                    @endforeach
                </select>
            @endif
        </x-slot:filters>
    </x-gp247::list-toolbar>

    <x-gp247::table :empty="$rows->isEmpty() ? gp247_language_render('admin.no_records') : null">
        <x-slot:head>
            <tr>
                <th class="w-10 px-4 py-3"></th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ gp247_language_render('Plugins/ProductRating::lang.admin.product') }}
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ gp247_language_render('Plugins/ProductRating::lang.admin.customer') }}
                </th>
                <x-gp247::th-sort field="rating" :sort-field="$sortField" :sort-dir="$sortDir">
                    {{ gp247_language_render('Plugins/ProductRating::lang.admin.rating') }}
                </x-gp247::th-sort>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ gp247_language_render('Plugins/ProductRating::lang.admin.content') }}
                </th>
                <x-gp247::th-sort field="status" :sort-field="$sortField" :sort-dir="$sortDir">
                    {{ gp247_language_render('Plugins/ProductRating::lang.admin.status') }}
                </x-gp247::th-sort>
                <x-gp247::th-sort field="created_at" :sort-field="$sortField" :sort-dir="$sortDir">
                    {{ gp247_language_render('Plugins/ProductRating::lang.admin.created_at') }}
                </x-gp247::th-sort>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ gp247_language_render('admin.actions') }}
                </th>
            </tr>
        </x-slot:head>

        @foreach ($rows as $row)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50" wire:key="review-{{ $row->id }}">
                <td class="px-4 py-3">
                    @if ($this->canDeleteReviews())
                        <x-gp247::select-check :value="$row->id" />
                    @endif
                </td>

                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                    {{-- Straight to the product on the storefront: moderating a
                         review means seeing what it is about. Falls back to plain
                         text when the product no longer exists. --}}
                    @if ($row->product_url)
                        <a href="{{ $row->product_url }}" target="_blank" rel="noopener"
                            class="font-medium text-blue-600 hover:underline dark:text-blue-400"
                            title="{{ gp247_language_render('Plugins/ProductRating::lang.admin.view_product') }}"
                            data-testid="product-rating-product-link">{{ $row->product_name }}</a>
                    @else
                        <span class="font-medium text-gray-800 dark:text-gray-100">{{ $row->product_name }}</span>
                    @endif
                    @if ($this->storeScopeUiVisible())
                        <span class="block text-xs text-gray-400">{{ $this->storeLabel($row->store_id) }}</span>
                    @endif
                </td>

                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                    {{ $row->customer_name }}
                    @if ($row->isVerifiedPurchase())
                        <span class="block text-xs text-emerald-600 dark:text-emerald-400">
                            <i class="fas fa-check"></i> {{ gp247_language_render('Plugins/ProductRating::lang.verified_purchase') }}
                        </span>
                    @endif
                </td>

                <td class="whitespace-nowrap px-4 py-3 text-sm text-amber-600 dark:text-amber-400">
                    <i class="fas fa-star"></i> {{ $row->rating }}
                </td>

                <td class="max-w-xs px-4 py-3 text-xs text-gray-600 dark:text-gray-300" title="{{ $row->content }}">
                    {{ \Illuminate\Support\Str::limit((string) $row->content, 120) }}
                    @if ($row->images->isNotEmpty())
                        <span class="block text-gray-400">
                            <i class="fas fa-image"></i> {{ $row->images->count() }}
                        </span>
                    @endif
                    @if ($row->reject_reason)
                        <span class="mt-1 block text-gray-500 dark:text-gray-400">
                            <i class="fas fa-ban"></i>
                            {{ $rejectReasons[$row->reject_reason] ?? $row->reject_reason }}
                            @if ($row->reject_note)<em>— {{ $row->reject_note }}</em>@endif
                        </span>
                    @endif
                    @if ($row->hasReply())
                        <span class="mt-1 block text-blue-600 dark:text-blue-400">
                            <i class="fas fa-reply"></i>
                            {{ \Illuminate\Support\Str::limit((string) $row->reply_content, 80) }}
                        </span>
                    @endif
                    {{-- Only a PUBLISHED review can be linked to: the anchor would
                         otherwise land on a page where it is not shown, which reads
                         as a broken link. --}}
                    @if ($row->product_url && !$row->trashed()
                        && $row->status == \App\GP247\Plugins\ProductRating\Models\ProductReview::STATUS_APPROVED)
                        <a href="{{ $row->product_url }}#review-{{ $row->id }}" target="_blank" rel="noopener"
                            class="mt-1 inline-flex items-center gap-1 text-blue-600 hover:underline dark:text-blue-400"
                            data-testid="product-rating-review-link">
                            <i class="fas fa-arrow-up-right-from-square"></i>
                            {{ gp247_language_render('Plugins/ProductRating::lang.admin.view_review') }}
                        </a>
                    @endif
                </td>

                <td class="px-4 py-3">
                    @if ($row->trashed())
                        {{-- Tombstone: the customer took it down, but the row still
                             holds that order's review slot. --}}
                        <x-gp247::badge color="red">
                            {{ gp247_language_render('Plugins/ProductRating::lang.admin.status_retracted') }}
                        </x-gp247::badge>
                    @else
                        <x-gp247::badge :color="$statusColors[$row->status] ?? 'gray'">
                            {{ $statusLabels[$row->status] ?? $row->status }}
                        </x-gp247::badge>
                    @endif
                </td>

                <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                    {{ $row->created_at }}
                </td>

                <td class="px-4 py-3">
                    <x-gp247::row-actions :delete-id="$this->canDeleteReviews() ? $row->id : null"
                        :delete-confirm="gp247_language_render('action.delete_confirm')">
                        @unless ($row->trashed())
                            @if ($row->status != \App\GP247\Plugins\ProductRating\Models\ProductReview::STATUS_APPROVED)
                                <x-gp247::button size="sm" variant="ghost" wire:click="approve('{{ $row->id }}')"
                                    title="{{ gp247_language_render('Plugins/ProductRating::lang.admin.approve') }}">
                                    <i class="fas fa-check text-green-600"></i>
                                </x-gp247::button>
                            @endif
                            @if ($row->status != \App\GP247\Plugins\ProductRating\Models\ProductReview::STATUS_REJECTED)
                                <x-gp247::button size="sm" variant="ghost" wire:click="startReject('{{ $row->id }}')"
                                    title="{{ gp247_language_render('Plugins/ProductRating::lang.admin.reject') }}">
                                    <i class="fas fa-ban text-gray-500"></i>
                                </x-gp247::button>
                            @endif
                            {{-- The shop's answer: the tool a seller is meant to have
                                 instead of the power to erase a bad review. --}}
                            <x-gp247::button size="sm" variant="ghost" wire:click="startReply('{{ $row->id }}')"
                                title="{{ gp247_language_render('Plugins/ProductRating::lang.admin.reply') }}">
                                <i class="fas fa-reply text-blue-600"></i>
                            </x-gp247::button>
                        @endunless
                    </x-gp247::row-actions>
                </td>
            </tr>

            @if ($rejectingId === (int) $row->id)
                <tr wire:key="reject-{{ $row->id }}">
                    <td colspan="8" class="bg-gray-50 px-4 py-3 dark:bg-gray-700/50">
                        <p class="mb-2 text-sm font-medium text-gray-800 dark:text-gray-100">
                            {{ gp247_language_render('Plugins/ProductRating::lang.admin.reject_reason') }}
                        </p>
                        {{-- A closed list: every reason is about the CONTENT and
                             applies whatever score the review carries. "Low score"
                             is deliberately not an option. --}}
                        <div class="mb-3 flex flex-wrap gap-3">
                            @foreach ($rejectReasons as $code => $label)
                                <label class="flex items-center gap-1 text-sm text-gray-700 dark:text-gray-200">
                                    <input type="radio" wire:model="rejectReason" value="{{ $code }}"
                                        data-testid="product-rating-reject-reason-{{ $code }}">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <input type="text" wire:model="rejectNote" maxlength="255"
                            placeholder="{{ gp247_language_render('Plugins/ProductRating::lang.admin.reject_note') }}"
                            class="mb-3 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            data-testid="product-rating-reject-note">
                        <div class="flex items-center gap-2">
                            <x-gp247::button size="sm" variant="danger" wire:click="confirmReject"
                                data-testid="product-rating-reject-confirm">
                                {{ gp247_language_render('Plugins/ProductRating::lang.admin.reject') }}
                            </x-gp247::button>
                            <x-gp247::button size="sm" variant="ghost" wire:click="cancelReject">
                                {{ gp247_language_render('Plugins/ProductRating::lang.admin.cancel') }}
                            </x-gp247::button>
                        </div>
                    </td>
                </tr>
            @endif

            @if ($replyingId === (int) $row->id)
                <tr wire:key="reply-{{ $row->id }}">
                    <td colspan="8" class="bg-gray-50 px-4 py-3 dark:bg-gray-700/50">
                        <p class="mb-2 text-sm font-medium text-gray-800 dark:text-gray-100">
                            {{ gp247_language_render('Plugins/ProductRating::lang.admin.reply') }}
                        </p>
                        <textarea wire:model="replyContent" rows="3" maxlength="1000"
                            placeholder="{{ gp247_language_render('Plugins/ProductRating::lang.admin.reply_placeholder') }}"
                            class="mb-3 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            data-testid="product-rating-reply-content"></textarea>
                        <div class="flex items-center gap-2">
                            <x-gp247::button size="sm" wire:click="saveReply"
                                data-testid="product-rating-reply-save">
                                {{ gp247_language_render('action.save') }}
                            </x-gp247::button>
                            <x-gp247::button size="sm" variant="ghost" wire:click="cancelReply">
                                {{ gp247_language_render('Plugins/ProductRating::lang.admin.cancel') }}
                            </x-gp247::button>
                        </div>
                    </td>
                </tr>
            @endif
        @endforeach
    </x-gp247::table>

    <div class="mt-4">{{ $rows->links('gp247-admin::partials.pagination') }}</div>
</div>
