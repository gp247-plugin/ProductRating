{{--
    Review statistics. Top: KPI tiles. Then the per-store comparison table (the
    reason this screen exists on a multi-store site), the score distribution and
    the most-reviewed products.

    Classes are restricted to what the core admin bundle already ships or
    safelists — plugin views are never scanned by core's Tailwind build
    (.claude/rules/gp247.md §3a).

    Variables: $totals, $byStore, $distribution, $distributionMax, $topProducts,
               $ratingMax.
--}}
<div>
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-gp247::stat-card color="sky" icon="fas fa-comments"
            :label="gp247_language_render('Plugins/ProductRating::lang.admin.total_reviews')"
            :value="number_format($totals['total'])" />

        <x-gp247::stat-card color="emerald" icon="fas fa-check"
            :label="gp247_language_render('Plugins/ProductRating::lang.admin.approved_reviews')"
            :value="number_format($totals['approved'])" />

        <x-gp247::stat-card color="amber" icon="fas fa-hourglass-half"
            :label="gp247_language_render('Plugins/ProductRating::lang.admin.pending_reviews')"
            :value="number_format($totals['pending'])" />

        <x-gp247::stat-card color="amber" icon="fas fa-star"
            :label="gp247_language_render('Plugins/ProductRating::lang.admin.average_rating')"
            :value="$totals['average'] . ' / ' . $ratingMax" />
    </div>

    {{-- Per-store breakdown --}}
    <x-gp247::card class="mb-6" :title="gp247_language_render('Plugins/ProductRating::lang.admin.by_store')">
        <x-gp247::table :empty="$byStore->isEmpty() ? gp247_language_render('admin.no_records') : null">
            <x-slot:head>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ gp247_language_render('Plugins/ProductRating::lang.admin.store') }}
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ gp247_language_render('Plugins/ProductRating::lang.admin.total_reviews') }}
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ gp247_language_render('Plugins/ProductRating::lang.admin.approved_reviews') }}
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ gp247_language_render('Plugins/ProductRating::lang.admin.pending_reviews') }}
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ gp247_language_render('Plugins/ProductRating::lang.admin.average_rating') }}
                    </th>
                </tr>
            </x-slot:head>

            @foreach ($byStore as $row)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50" wire:key="store-{{ $row->store_id }}">
                    <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-100">
                        {{ $this->storeLabel($row->store_id) ?: $row->store_id }}
                    </td>
                    <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-200">
                        {{ number_format($row->total) }}
                    </td>
                    <td class="px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-200">
                        {{ number_format($row->approved) }}
                    </td>
                    <td class="px-4 py-3 text-right text-sm">
                        @if ($row->pending > 0)
                            <x-gp247::badge color="amber">{{ number_format($row->pending) }}</x-gp247::badge>
                        @else
                            <span class="text-gray-400">0</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-amber-600 dark:text-amber-400">
                        <i class="fas fa-star"></i> {{ round((float) $row->average, 1) }}
                    </td>
                </tr>
            @endforeach
        </x-gp247::table>
    </x-gp247::card>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Score distribution --}}
        <x-gp247::card :title="gp247_language_render('Plugins/ProductRating::lang.admin.distribution')">
            <div class="space-y-3">
                @foreach ($distribution as $score => $count)
                    <div class="flex items-center gap-3">
                        <span class="whitespace-nowrap text-sm text-amber-600 dark:text-amber-400">
                            <i class="fas fa-star"></i> {{ $score }}
                        </span>
                        <span class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <span class="block h-2 rounded-full bg-amber-500"
                                style="width: {{ $count > 0 ? round($count * 100 / $distributionMax) : 0 }}%"></span>
                        </span>
                        <span class="whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                            {{ number_format($count) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </x-gp247::card>

        {{-- Most reviewed products --}}
        <x-gp247::card :title="gp247_language_render('Plugins/ProductRating::lang.admin.top_products')">
            @if ($topProducts === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.no_records') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($topProducts as $product)
                        <div class="flex items-center justify-between gap-3">
                            <span class="min-w-0 flex-1 truncate text-sm text-gray-700 dark:text-gray-200"
                                title="{{ $product['name'] }}">{{ $product['name'] }}</span>
                            <span class="whitespace-nowrap text-sm text-amber-600 dark:text-amber-400">
                                <i class="fas fa-star"></i> {{ $product['average'] }}
                            </span>
                            <span class="whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                {{ gp247_language_render('Plugins/ProductRating::lang.admin.n_reviews', ['count' => $product['total']]) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-gp247::card>
    </div>
</div>
