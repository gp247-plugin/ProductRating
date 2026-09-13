{{--
    Storefront review box: rating summary, review form (when allowed) and the
    published reviews of this product.

    Styling uses ONLY the semantic component classes and utilities that the
    GP247Front bundle already contains (.btn-primary, .input, .label, .card,
    .badge-*, .divider, .section-title …). That bundle is compiled statically and
    is not rebuilt when a plugin ships new markup, so a brand-new utility would
    have no rule behind it (.claude/rules/gp247.md §3b). Stars therefore use the
    template's brand color rather than an amber that is not in the bundle.

    A template developer can override this file at
    <Template>/livewire/productrating_review-box.blade.php (ADR-011).

    Variables: $reviews, $reviewTotal, $hasMore, $summary, $ratingMax,
               $blockedReason, $imagesAllowed, $imageMax.
--}}
<div class="mt-10" data-testid="product-rating-review-box">
    <h2 class="section-title mb-4">{{ gp247_language_render('Plugins/ProductRating::lang.front.heading') }}</h2>

    {{-- Summary --}}
    <div class="card p-4 mb-6">
        <div class="flex flex-wrap items-center gap-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-ink-900" data-testid="product-rating-average">
                    {{ $summary['average'] }}<span class="text-sm text-ink-400"> / {{ $ratingMax }}</span>
                </div>
                <div class="flex items-center gap-1 mt-1">
                    @for ($i = 1; $i <= $ratingMax; $i++)
                        @include('Plugins/ProductRating::livewire._star', [
                            'starClass' => 'w-4 h-4 ' . ($i <= round($summary['average']) ? 'text-brand-600' : 'text-ink-300'),
                        ])
                    @endfor
                </div>
                <div class="text-xs text-ink-400 mt-1" data-testid="product-rating-total">
                    {{ gp247_language_render('Plugins/ProductRating::lang.front.n_reviews', ['count' => $summary['total']]) }}
                </div>
            </div>

            <div class="flex-1">
                @for ($score = $ratingMax; $score >= 1; $score--)
                    @php
                        $count = $summary['distribution'][$score] ?? 0;
                        $percent = $summary['total'] > 0 ? round($count * 100 / $summary['total']) : 0;
                    @endphp
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs text-ink-600">{{ $score }}</span>
                        @include('Plugins/ProductRating::livewire._star', ['starClass' => 'w-4 h-4 text-brand-600'])
                        <span class="flex-1 h-2 rounded-full bg-ink-50 overflow-hidden">
                            <span class="block h-2 rounded-full bg-brand-600" style="width: {{ $percent }}%"></span>
                        </span>
                        <span class="text-xs text-ink-400">{{ $count }}</span>
                    </div>
                @endfor
            </div>
        </div>
    </div>

    {{-- The customer's own review: theirs to see, change or take down, whatever
         state moderation left it in. --}}
    @if ($ownReview && !$editing)
        <div class="card p-4 mb-6" data-testid="product-rating-own">
            <div class="flex items-center justify-between gap-3 mb-2">
                <span class="text-sm font-medium text-ink-800">
                    {{ gp247_language_render('Plugins/ProductRating::lang.front.your_review_heading') }}
                </span>
                <span class="text-xs text-ink-400">{{ $ownReview->created_at }}</span>
            </div>

            <div class="flex items-center gap-1 mb-2">
                @for ($i = 1; $i <= $ratingMax; $i++)
                    @include('Plugins/ProductRating::livewire._star', [
                        'starClass' => 'w-4 h-4 ' . ($i <= $ownReview->rating ? 'text-brand-600' : 'text-ink-300'),
                    ])
                @endfor
            </div>

            @if ($ownReview->content)
                <p class="text-sm text-ink-700 leading-relaxed">{{ $ownReview->content }}</p>
            @endif

            @if ($ownReview->status == \App\GP247\Plugins\ProductRating\Models\ProductReview::STATUS_PENDING)
                <p class="text-xs text-ink-400 mt-2" data-testid="product-rating-own-pending">
                    {{ gp247_language_render('Plugins/ProductRating::lang.front.pending_notice') }}
                </p>
            @elseif ($ownReview->status == \App\GP247\Plugins\ProductRating\Models\ProductReview::STATUS_REJECTED)
                <p class="text-xs text-ink-400 mt-2" data-testid="product-rating-own-rejected">
                    {{ gp247_language_render('Plugins/ProductRating::lang.front.rejected_notice') }}
                </p>
            @endif

            <div class="flex items-center gap-2 mt-3">
                <button type="button" wire:click="editOwn" class="btn-outline btn-sm"
                    data-testid="product-rating-own-edit">
                    {{ gp247_language_render('Plugins/ProductRating::lang.front.edit') }}
                </button>
                <button type="button" wire:click="removeOwn" class="btn-ghost btn-sm"
                    wire:confirm="{{ gp247_language_render('Plugins/ProductRating::lang.front.remove_confirm') }}"
                    data-testid="product-rating-own-remove">
                    {{ gp247_language_render('Plugins/ProductRating::lang.front.remove') }}
                </button>
            </div>
        </div>
    @endif

    @if ($ownNotice)
        <div class="card p-4 mb-6" data-testid="product-rating-own-notice">
            <p class="text-sm text-ink-700">
                {{ gp247_language_render('Plugins/ProductRating::lang.front.' . $ownNotice) }}
            </p>
        </div>
    @endif

    {{-- Form / why the form is not available --}}
    @if ($editing)
        {{-- Same fields as a new review; the write path is updateOwn(), which
             sends the edit back through moderation. --}}
        <form wire:submit="updateOwn" class="card p-4 mb-6" data-testid="product-rating-edit-form">
            <div class="mb-4">
                <span class="label">{{ gp247_language_render('Plugins/ProductRating::lang.front.your_rating') }}</span>
                <div class="flex items-center gap-1">
                    @for ($i = 1; $i <= $ratingMax; $i++)
                        <label class="cursor-pointer" title="{{ $i }}">
                            <input type="radio" wire:model.live="rating" value="{{ $i }}" class="sr-only"
                                data-testid="product-rating-edit-star-{{ $i }}">
                            @include('Plugins/ProductRating::livewire._star', [
                                'starClass' => 'w-5 h-5 ' . ($i <= $rating ? 'text-brand-600' : 'text-ink-300'),
                            ])
                        </label>
                    @endfor
                </div>
                @error('rating')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <textarea wire:model="content" rows="4" class="input"
                    data-testid="product-rating-edit-content"></textarea>
                @error('content')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="btn-primary" data-testid="product-rating-edit-save">
                    {{ gp247_language_render('Plugins/ProductRating::lang.front.save_changes') }}
                </button>
                <button type="button" wire:click="cancelEdit" class="btn-ghost btn-sm">
                    {{ gp247_language_render('Plugins/ProductRating::lang.front.cancel') }}
                </button>
            </div>
        </form>
    @elseif ($ownReview)
        {{-- Their review is already shown above with its own controls, so the
             generic "you already reviewed this" message would just repeat it. --}}
    @elseif ($submitted)
        <div class="card p-4 mb-6" data-testid="product-rating-thanks">
            <p class="text-sm text-ink-700">
                {{ gp247_language_render('Plugins/ProductRating::lang.front.thanks') }}
            </p>
        </div>
    @elseif ($blockedReason === 'guest')
        <div class="card p-4 mb-6" data-testid="product-rating-login-required">
            <p class="text-sm text-ink-700 mb-3">
                {{ gp247_language_render('Plugins/ProductRating::lang.front.login_required') }}
            </p>
            <a href="{{ gp247_route_front('customer.login') }}" class="btn-primary btn-sm"
                data-testid="product-rating-login-link">
                {{ gp247_language_render('front.login') }}
            </a>
        </div>
    @elseif ($blockedReason === 'already_reviewed')
        <div class="card p-4 mb-6" data-testid="product-rating-already-reviewed">
            <p class="text-sm text-ink-600">
                {{ gp247_language_render('Plugins/ProductRating::lang.front.already_reviewed') }}
            </p>
        </div>
    @elseif ($blockedReason === 'not_purchased')
        <div class="card p-4 mb-6" data-testid="product-rating-purchase-required">
            <p class="text-sm text-ink-600">
                {{ gp247_language_render('Plugins/ProductRating::lang.front.purchase_required') }}
            </p>
        </div>
    @else
        <form wire:submit="submit" class="card p-4 mb-6" data-testid="product-rating-form">
            {{-- Score picker: plain radio inputs styled as stars, so it works
                 without JavaScript and stays reachable by keyboard. --}}
            <div class="mb-4">
                <span class="label">{{ gp247_language_render('Plugins/ProductRating::lang.front.your_rating') }}</span>
                <div class="flex items-center gap-1" data-testid="product-rating-stars">
                    @for ($i = 1; $i <= $ratingMax; $i++)
                        <label class="cursor-pointer" title="{{ $i }}">
                            <input type="radio" wire:model.live="rating" value="{{ $i }}" class="sr-only"
                                data-testid="product-rating-star-{{ $i }}">
                            @include('Plugins/ProductRating::livewire._star', [
                                'starClass' => 'w-5 h-5 ' . ($i <= $rating ? 'text-brand-600' : 'text-ink-300'),
                            ])
                        </label>
                    @endfor
                </div>
                @error('rating')
                    <p class="text-xs text-red-600 mt-1" data-testid="product-rating-error-rating">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="label" for="productrating-content">
                    {{ gp247_language_render('Plugins/ProductRating::lang.front.your_review') }}
                </label>
                <textarea id="productrating-content" wire:model="content" rows="4" class="input"
                    placeholder="{{ gp247_language_render('Plugins/ProductRating::lang.front.review_placeholder') }}"
                    data-testid="product-rating-content"></textarea>
                @error('content')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            @if ($imagesAllowed)
                <div class="mb-4">
                    <label class="label" for="productrating-photos">
                        {{ gp247_language_render('Plugins/ProductRating::lang.front.add_photos', ['count' => $imageMax]) }}
                    </label>
                    <input id="productrating-photos" type="file" wire:model="photos" multiple
                        accept="image/jpeg,image/png,image/webp" class="input"
                        data-testid="product-rating-photos">
                    <div wire:loading wire:target="photos" class="text-xs text-ink-400 mt-1">
                        {{ gp247_language_render('Plugins/ProductRating::lang.front.uploading') }}
                    </div>
                    @error('photos.*')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror

                    @if ($photos)
                        <div class="flex flex-wrap gap-2 mt-3">
                            @foreach ($photos as $photo)
                                @if (method_exists($photo, 'temporaryUrl'))
                                    <img src="{{ $photo->temporaryUrl() }}" alt=""
                                        class="w-16 h-16 rounded-lg overflow-hidden border object-cover">
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <button type="submit" class="btn-primary" data-testid="product-rating-submit"
                wire:loading.attr="disabled" wire:target="submit">
                {{ gp247_language_render('Plugins/ProductRating::lang.front.submit') }}
            </button>
        </form>
    @endif

    {{-- Published reviews --}}
    @if ($reviews->isEmpty())
        <p class="text-sm text-ink-400" data-testid="product-rating-empty">
            {{ gp247_language_render('Plugins/ProductRating::lang.front.no_reviews') }}
        </p>
    @else
        <div class="space-y-4">
            @foreach ($reviews as $review)
                <div id="review-{{ $review->id }}" class="card p-4" wire:key="review-{{ $review->id }}" data-testid="product-rating-item">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-ink-800">{{ $review->customer_name }}</span>
                            @if ($review->isVerifiedPurchase())
                                <span class="badge-verified" data-testid="product-rating-verified">
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

                    @if ($review->content)
                        <p class="text-sm text-ink-700 leading-relaxed">{{ $review->content }}</p>
                    @endif

                    @if ($review->images->isNotEmpty())
                        <div class="flex flex-wrap gap-2 mt-3">
                            @foreach ($review->images as $image)
                                <a href="{{ $image->getUrl() }}" target="_blank" rel="noopener"
                                    class="w-16 h-16 rounded-lg overflow-hidden border bg-white block">
                                    <img src="{{ $image->getUrl() }}" alt=""
                                        class="w-full h-full object-cover">
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
                    data-testid="product-rating-show-more">
                    {{ gp247_language_render('Plugins/ProductRating::lang.front.show_more') }}
                </button>
            </div>
        @endif
    @endif
</div>
