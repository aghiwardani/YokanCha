@props([
    'style' => 'klasik',
    'size' => 'sm',
    'label' => '250 ml',
])

@php
    // Layer geometry from Figma "Botol" frames, in units of the small (108.571 × 190)
    // card bottle. The large featured bottle (188.571 × 330) is the same drawing at
    // 1.7368×, so every coordinate is multiplied by $s.
    $s = $size === 'lg' ? 330 / 190 : 1;
    $px = fn (float $v) => round($v * $s, 3).'px';
    $dir = asset('images/landing/bottle');

    // Liquid layers per style: [asset, top, height]; left 21.26 / width 66.048 for all.
    // Maple only exists as a large drawing in Figma, so its large assets are reused (scaled).
    $fills = [
        'maple' => [
            ['maple-fill-1-lg.svg', 282.98 / (330 / 190), 41.518 / (330 / 190)],
            ['maple-fill-2-lg.svg', 156.26 / (330 / 190), 127.663 / (330 / 190)],
            ['maple-fill-3-lg.svg', 138.16 / (330 / 190), 19.046 / (330 / 190)],
        ],
        'klasik' => [
            ['klasik-fill-1.svg', 89.97, 96.863],
            ['klasik-fill-2.svg', 79.55, 10.966],
        ],
        'oat' => [
            ['oat-fill-1.svg', 89.97, 96.863],
            ['oat-fill-2.svg', 79.55, 10.966],
        ],
        'murni' => [
            ['murni-fill-1.svg', 82.15, 104.682],
        ],
        'strawberry' => [
            ['strawberry-fill-1.svg', 160.32, 26.51],
            ['strawberry-fill-2.svg', 144.69, 16.177],
            ['strawberry-fill-3.svg', 84.76, 60.475],
        ],
    ][$style] ?? null;

    $fills ??= [['klasik-fill-1.svg', 89.97, 96.863], ['klasik-fill-2.svg', 79.55, 10.966]];

    $glass = $size === 'lg' ? 'glass-lg.svg' : 'glass.svg';
    $outline = $size === 'lg' ? 'outline-lg.svg' : 'outline.svg';
@endphp

<div {{ $attributes->merge(['class' => 'relative shrink-0']) }} style="width: {{ $px(108.571) }}; height: {{ $px(190) }};" aria-hidden="true">
    {{-- Kaca: the asset is slightly larger than its box (inset -0.27% / -0.69%). --}}
    <div class="absolute" style="left: {{ $px(21.714) }}; top: {{ $px(21.714) }}; width: {{ $px(65.143) }}; height: {{ $px(164.667) }};">
        <img src="{{ $dir }}/{{ $glass }}" alt="" class="absolute max-w-none" style="left: -0.69%; top: -0.27%; width: 101.38%; height: 100.54%;">
    </div>

    @foreach ($fills as [$file, $top, $height])
        <img src="{{ $dir }}/{{ $file }}" alt="" class="absolute max-w-none" style="left: {{ $px(21.262) }}; top: {{ $px($top) }}; width: {{ $px(66.048) }}; height: {{ $px($height) }};">
    @endforeach

    <img src="{{ $dir }}/{{ $outline }}" alt="" class="absolute max-w-none" style="left: {{ $px(21.714) }}; top: {{ $px(21.714) }}; width: {{ $px(65.143) }}; height: {{ $px(164.667) }};">

    {{-- Tutup --}}
    <div class="absolute box-border border-solid border-charcoal bg-brand" style="left: {{ $px(38) }}; top: {{ $px(5.429) }}; width: {{ $px(32.571) }}; height: {{ $px(18.095) }}; border-width: {{ $px(1.81) }}; border-radius: {{ $px(4.524) }};"></div>

    {{-- Label --}}
    <div class="absolute box-border border-solid border-charcoal bg-surface" style="left: {{ $px(28.048) }}; top: {{ $px(101.333) }}; width: {{ $px(52.476) }}; height: {{ $px(47.048) }}; border-width: {{ $px(1.448) }}; border-radius: {{ $px(5.429) }};"></div>

    <p class="absolute text-center font-display uppercase text-charcoal" style="left: {{ $px(28.048) }}; top: {{ $px(110.381) }}; width: {{ $px(52.476) }}; font-size: 8.4px; line-height: {{ $px(19.905) }}; letter-spacing: 0.6px;">YokanCha</p>
    <p class="absolute text-center font-mono font-medium text-charcoal" style="left: {{ $px(28.048) }}; top: {{ $px(127.571) }}; width: {{ $px(52.476) }}; font-size: {{ $px(9.5) }}; line-height: {{ $px(12.667) }};" data-bottle-size>{{ $label }}</p>
</div>
