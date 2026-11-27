{{--
    Yoka mascot image, sharp on high-density (retina / 4K) screens.
    Serves WebP at 480 px or full size (~2.8x the largest display) and lets the
    browser pick by the rendered width given in `sizes`; the PNG (~2x) is the
    fallback. Transparent background, same aspect ratio as the old artwork.

    Each pose has a coffee twin (yoka-coffee-{name}). The menu switches theme
    in place, so it carries both and CSS shows the one for <html data-theme>
    (the hidden one is lazy). Other pages show only their theme's mascot;
    a matcha + coffee order ("blend") keeps matcha Yoka.

    <x-mascot name="hero" sizes="339px" class="h-[464px] w-[339px]" />
--}}
@props(['name', 'sizes' => '340px', 'alt' => ''])

@php
    // Full-size width of each matcha WebP (public/images/landing/yoka-{name}.webp).
    $fullWidth = [
        'hero' => 940,
        'lean' => 1083,
        'thumbs' => 1024,
        'chasen' => 929,
    ][$name];
    $theme = view()->shared('ycTheme', 'matcha');
    $switchable = (bool) view()->shared('ycSwitchable', false);

    $images = [];
    if ($switchable || $theme !== 'coffee') {
        $images[] = ['base' => asset("images/landing/yoka-{$name}"), 'width' => $fullWidth,
            'class' => $switchable ? 'yc-mascot-matcha' : '', 'lazy' => $switchable && $theme === 'coffee'];
    }
    if ($switchable || $theme === 'coffee') {
        $images[] = ['base' => asset("images/landing/yoka-coffee-{$name}"), 'width' => 700,
            'class' => 'yc-mascot-coffee', 'lazy' => $switchable && $theme !== 'coffee'];
    }
@endphp

@foreach ($images as $image)
    <img
        src="{{ $image['base'] }}.png"
        srcset="{{ $image['base'] }}-480.webp 480w, {{ $image['base'] }}.webp {{ $image['width'] }}w"
        sizes="{{ $sizes }}"
        alt="{{ $alt }}"
        decoding="async"
        @if ($image['lazy']) loading="lazy" @endif
        {{ $attributes->class($image['class']) }}
    >
@endforeach
