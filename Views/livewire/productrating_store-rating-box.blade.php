{{--
    Shop reputation panel for a vendor/shop storefront page: headline average,
    per-score filter chips, the shop's most-reviewed products, and the reviews
    themselves — each labelled with the product it is about.

    Same styling constraint as the product review box: only classes the
    GP247Front bundle already ships, and stars are inline SVG because the
    template loads no icon font (.claude/rules/gp247.md §3b).

    A template developer can override this at
    <Template>/livewire/productrating_store-rating-box.blade.php (ADR-011).

    Variables: $summary, $products, $reviews, $filteredTotal, $hasMore, $ratingMax.
--}}
<div class="mt-6" data-testid="store-rating-box">
    {{-- Headline + per-score filters --}}
    <div class="card p-4 mb-6">
        <div class="flex flex-wrap items-center gap-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-ink-900" data-testid="store-rating-average">
                    {{ $summary['average'] }}<span class="text-sm text-ink-400"> / {{ $ratingMax }}</span>
                </div>
                <div class="flex items-center gap-1 mt-1">
                    @for ($i = 1; $i <= $ratingMax; $i++)
                        @include('Plugins/ProductRating::livewire._star', [
                            'starClass' => 'w-4 h-4 ' . ($i <= round($summary['average']) ? 'text-brand-600' : 'text-ink-300'),
                        ])
                    @endfor
                </div>
                <div class="text-xs text-ink-400 mt-1" data-testid="store-rating-total">
                    {{ gp247_language_render('Plugins/ProductRating::lang.front.n_reviews', ['count' => $summary['total']]) }}
                </div>
            </div>

            <div class="flex-1">
                <div class="flex flex-wrap gap-2" data-testid="store-rating-filters">
                    <button type="button" wire:click="filterScore('')"
                        class="{{ $scoreFilter === '' ? 'btn-primary btn-sm' : 'btn-outline btn-sm' }}"
                        data-testid="store-rating-filter-all">
                        {{ gp247_language_render('Plugins/ProductRating::lang.front.all_scores') }}
                        ({{ $summary['total'] }})
                    </button>

                    @for ($score = $ratingMax; $score >= 1; $score--)
                        <button type="button" wire:click="filterScore({{ $score }})"
                            class="{{ (string) $scoreFilter === (string) $score ? 'btn-primary btn-sm' : 'btn-outline btn-sm' }}"
                            data-testid="store-rating-filter-{{ $score }}">
                            {{ $score }}
                            @include('Plugins/ProductRating::livewire._star', ['starClass' => 'w-4 h-4'])
                            ({{ $summary['distribution'][$score] ?? 0 }})
                        </button>
                    @endfor
                </div>
            </div>
        </div>
    </div>

    {{-- Which products the shop is rated on --}}
    @if ($products !== [])
        <div class="card p-4 mb-6" data-testid="store-rating-products">
            <h3 class="text-sm font-medium text-ink-800 mb-3">
                {{ gp247_language_render('Plugins/ProductRating::lang.front.rated_products') }}
            </h3>
            <div class="space-y-3">
                @foreach ($products as $product)
                    <div class="flex items-center justify-between gap-3" wire:key="rated-{{ $product['product_id'] }}">
                        <span class="flex-1 text-sm text-ink-700">{{ $product['name'] }}</span>
                        <span class="flex items-center gap-1 text-sm text-ink-800">
                            @include('Plugins/ProductRating::livewire._star', ['starClass' => 'w-4 h-4 text-brand-600'])
                            {{ $product['average'] }}
                        </span>
                        <span class="text-xs text-ink-400">
                            {{ gp247_language_render('Plugins/ProductRating::lang.front.n_reviews', ['count' => $product['total']]) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- The reviews --}}
    @if ($reviews->isEmpty())
        <p class="text-sm text-ink-400" data-testid="store-rating-empty">
            {{ gp247_language_render('Plugins/ProductRating::lang.front.no_store_reviews') }}
        </p>
    @else
        <div class="space-y-4">
            @foreach ($reviews as $review)
                <div id="review-{{ $review->id }}" class="card p-4" wire:key="store-review-{{ $review->id }}" data-testid="store-rating-item">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-ink-800">{{ $review->customer_name }}</span>
                            @if ($review->isVerifiedPurchase())
                                <span class="badge-verified">
                                    {{ gp247_language_render('Plugins/ProductRating::lang.verified_purchase') }}
                                </span>
                            @endif
                        </div>
                        <span class="text-xs text-ink-400">{{ $review->created_at }}</span>
                    </div>

                    <div class="flex items-center gap-1 mb-2">
                        @for ($i = 1; $i <= $ratingMax; $i++)
                            @include('Plugins/ProductRating::livewire._star', [
                                'starClass' => 'w-4 h-4 ' . ($i <= $review->rating ? 'text-brand-600' : 'text-ink-300'),
                            ])
                        @endfor
                    </div>

                    @if ($review->product_name)
                        <p class="text-xs text-ink-400 mb-2" data-testid="store-rating-item-product">
                            {{ $review->product_name }}
                        </p>
                    @endif

                    @if ($review->content)
                        <p class="text-sm text-ink-700 leading-relaxed">{{ $review->content }}</p>
                    @endif

                    @if ($review->images->isNotEmpty())
                        <div class="flex flex-wrap gap-2 mt-3">
                            @foreach ($review->images as $image)
                                <a href="{{ $image->getUrl() }}" target="_blank" rel="noopener"
                                    class="w-16 h-16 rounded-lg overflow-hidden border bg-white block">
                                    <img src="{{ $image->getUrl() }}" alt="" class="w-full h-full object-cover">
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if ($review->hasReply())
                        {{-- The shop's public answer. Shown under the review it
                             answers, never in place of it. --}}
                        <div class="mt-3 rounded-lg border border-ink-100 bg-ink-50 p-3">
                            <p class="text-xs font-medium text-ink-800 mb-1">
                                {{ gp247_language_render('Plugins/ProductRating::lang.front.shop_reply') }}
                            </p>
                            <p class="text-sm text-ink-700 leading-relaxed">{{ $review->reply_content }}</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($hasMore)
            <div class="mt-4 text-center">
                <button type="button" wire:click="showMore" class="btn-outline btn-sm"
                    data-testid="store-rating-show-more">
                    {{ gp247_language_render('Plugins/ProductRating::lang.front.show_more') }}
                </button>
            </div>
        @endif
    @endif
</div>
