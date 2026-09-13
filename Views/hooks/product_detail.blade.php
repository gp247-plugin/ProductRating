{{--
    The review box, mounted into gp247/shop's product-detail extension point.

    Kept as its own view (rather than inline HTML in the hook class) so the
    Livewire component is mounted by Blade the normal way, and so a template can
    still restyle the box itself by overriding
    livewire/productrating_review-box.blade.php.

    Variables: $productId
--}}
@livewire('gp247-productrating-front::review-box', ['productId' => $productId], key('product-review-' . $productId))
