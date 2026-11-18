{{--
    Yoka mascot image, sharp on high-density (retina / 4K) screens.
    Serves WebP at 480 px or full size (~2.8x the largest display) and lets the
    browser pick by the rendered width given in `sizes`; the PNG (~2x) is the
    fallback. Transparent background, same aspect ratio as the old artwork.

    <x-mascot name="hero" sizes="339px" class="h-[464px] w-[339px]" />
--}}
@props(['name', 'sizes' => '340px', 'alt' => ''])

@php
    // Full-size width of each WebP (public/images/landing/yoka-{name}.webp).
    $fullWidth = [
        'hero' => 940,
        'lean' => 1083,
        'thumbs' => 1024,
        'chasen' => 929,
    ][$name];
    $base = asset("images/landing/yoka-{$name}");
@endphp

<img
    src="{{ $base }}.png"
    srcset="{{ $base }}-480.webp 480w, {{ $base }}.webp {{ $fullWidth }}w"
    sizes="{{ $sizes }}"
    alt="{{ $alt }}"
    decoding="async"
    {{ $attributes }}
>
