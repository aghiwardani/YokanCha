{{--
    Menu product card (Figma "Kartu/*", desktop 7:13 & 7:61, mobile 9:85).
    $item: PoPeriodProduct with ->product loaded; $availability: MenuController's ppp_id => {max, sizes}. Every card has the same layout and
    size so the grid stays even (the wide Figma "featured" card is not used).
--}}
@php
    $product = $item->product;
    // Counts already exclude what this visitor has in the cart.
    $stats = $availability[$item->id] ?? ['max' => 0, 'stock' => 0, 'limit' => 0, 'sizes' => [], 'cells' => []];
    $remaining = $stats['stock'];
    $limitLeft = $stats['limit'];
    $maxPerVariant = (int) config('yokancha.max_per_variant');
    $sizeStock = $stats['sizes'];
    $soldOut = $remaining <= 0;
    $capReached = ! $soldOut && $limitLeft <= 0;
    $sizes = $product->sizesSmallestFirst();
    // Least sweet first; the level closest to Normal (100%) is preselected.
    $sweetnessPercents = $product->sweetnessLeastFirst();
    $sweetness = array_keys($sweetnessPercents);
    $byNormalFirst = collect($sweetness)->sortBy(fn ($level) => [abs($sweetnessPercents[$level] - 100), $sweetnessPercents[$level]])->values();
    $firstSize = $sizes[0] ?? null;
    $firstEnabledSize = collect($sizes)->first(fn ($size) => ($sizeStock[$size] ?? 0) > 0);
    $cells = $availability[$item->id]['cells'] ?? [];
    $preSize = $firstEnabledSize ?? ($sizes[0] ?? '-');
    $sweetEnabled = fn ($level) => collect($cells)->contains(fn ($row) => ($row[$level] ?? 0) > 0);
    $firstEnabledSweet = $byNormalFirst->first(fn ($level) => ($cells[$preSize][$level] ?? 0) > 0)
        ?? $byNormalFirst->first($sweetEnabled);
    $price = $product->priceFor($firstSize);
    $rupiah = fn (int $amount) => 'Rp'.number_format($amount, 0, ',', '.');

    $preCell = (int) ($cells[$preSize][$firstEnabledSweet ?? '-'] ?? 0);
    $preMax = max(1, min($maxPerVariant, $limitLeft, $preCell));
    $canAdd = ! $soldOut && ! $capReached && $preCell > 0;
    $addLabel = $soldOut ? 'Stok habis' : ($capReached ? 'Batas pesanan' : ($canAdd ? 'Tambah' : 'Stok habis'));

    $tag = $soldOut ? 'Stok habis' :($product->tag ?: ($remaining <= 5 ? 'Stok terbatas' : null));

    $toneClass = [
        'amber' => 'bg-amber-soft',
        'sage' => 'bg-sage-soft',
        'oat' => 'bg-oat-deep',
    ][$product->tone] ?? 'bg-sage-soft';

    $bottleLabel = $firstSize ?? '250 ml';

    // Extra matcha per size (only sizes that offer it) and what is left with it.
    $extraPrices = $product->extraMatchaPrices();
    $extraCells = $stats['extraCells'] ?? [];
    $preExtra = isset($extraPrices[$preSize]) && ($extraCells[$preSize][$firstEnabledSweet ?? '-'] ?? 0) > 0;
@endphp

<article
    class="flex h-full flex-col overflow-hidden rounded-card border border-charcoal bg-surface"
    data-product-card
    data-ppp="{{ $item->id }}"
    data-cells='@json((object) $cells)'
    data-extra-cells='@json((object) $extraCells)'
    data-limit="{{ min($maxPerVariant, $limitLeft) }}"
>
    {{-- Foto --}}
    <div class="relative flex h-[220px] shrink-0 flex-col items-center justify-end overflow-hidden border-b border-charcoal pb-[25px] lg:h-[290px] lg:pb-[18px] {{ $toneClass }} {{ $soldOut ? 'grayscale-[60%]' : '' }}" data-card-photo>
        <img src="{{ asset('images/landing/bottle/circle.svg') }}" alt="" class="absolute left-1/2 top-[45px] h-[190px] w-[190px] max-w-none -translate-x-1/2 lg:top-[98px] lg:h-[240px] lg:w-[240px]">
        <x-bottle :style="$product->bottle_style" size="sm" :label="$bottleLabel" class="origin-bottom scale-[0.8947] lg:scale-100" />

        @if ($tag)
            <span class="absolute left-3 top-3 lg:left-[14px] lg:top-[14px] {{ $soldOut ? 'yc-tag' : 'yc-tag-amber' }}" data-card-tag>{{ $tag }}</span>
        @endif
        <span class="absolute left-3 top-3 lg:left-[14px] lg:top-[14px] yc-tag hidden" data-card-soldout>Stok habis</span>
    </div>

    {{-- Isi --}}
    <form method="POST" action="{{ route('cart.add') }}" data-cart-add class="flex flex-1 flex-col justify-between gap-3 p-[18px] lg:p-5">
        @csrf
        <input type="hidden" name="po_period_product_id" value="{{ $item->id }}">

        <div class="flex flex-col gap-3 lg:gap-[14px]">
            <h3 class="font-display text-[25px] leading-7 text-charcoal">{{ $product->name }}</h3>

            @if ($product->description)
                <p class="text-sm leading-[22px] text-muted">{{ $product->description }}</p>
            @endif

            <p class="sr-only">Sisa {{ max(0, $remaining) }} botol untuk batch ini.</p>

            @if ($soldOut)
                <p class="rounded-brand bg-oat-deep px-3 py-2 text-sm font-semibold text-charcoal" data-card-note>
                    Bahan untuk menu ini sudah habis di batch ini.
                </p>
            @elseif ($capReached)
                <p class="rounded-brand bg-amber-soft px-3 py-2 text-sm font-semibold text-amber-text" data-card-note>
                    Kamu sudah ambil {{ $maxPerVariant }} botol, batas per pesanan.
                </p>
            @endif

            @if (! empty($sizes))
                <fieldset class="flex flex-col gap-2">
                    <legend class="yc-mono mb-2 text-muted">Ukuran</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($sizes as $index => $size)
                            <label class="block">
                                <input
                                    type="radio"
                                    name="size"
                                    value="{{ $size }}"
                                    class="peer sr-only"
                                    data-price="{{ $rupiah($product->priceFor($size)) }}"
                                    data-base="{{ $product->priceFor($size) }}"
                                    data-extra="{{ $extraPrices[$size] ?? 0 }}"
                                    @checked($size === $firstEnabledSize)
                                    @disabled($soldOut || ($sizeStock[$size] ?? 0) <= 0)
                                >
                                <span class="yc-chip">
                                    <span class="text-sm font-bold leading-[18px]">{{ $size }}</span>
                                    <span class="yc-chip-sub" data-chip-sub data-label="{{ $rupiah($product->priceFor($size)) }}">{{ ($sizeStock[$size] ?? 0) > 0 ? $rupiah($product->priceFor($size)) : 'Habis' }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @else
                <input type="hidden" name="size" value="-">
            @endif

            @if (! empty($sweetness))
                <fieldset class="flex flex-col gap-2">
                    <legend class="yc-mono mb-2 text-muted">Manis</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($sweetness as $index => $level)
                            <label class="block">
                                <input
                                    type="radio"
                                    name="sweetness"
                                    value="{{ $level }}"
                                    class="peer sr-only"
                                    @checked($level === $firstEnabledSweet)
                                    @disabled($soldOut || ($cells[$preSize][$level] ?? 0) <= 0)
                                >
                                <span class="yc-chip">
                                    <span class="text-sm font-bold leading-[18px]">{{ $level }}</span>
                                    <span class="yc-chip-sub {{ ($cells[$preSize][$level] ?? 0) > 0 ? 'hidden' : '' }}" data-chip-sub data-label="">Habis</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @else
                <input type="hidden" name="sweetness" value="-">
            @endif

            @if ($extraPrices !== [])
                {{-- Extra matcha: price differs per size (master Ukuran); hidden for products whose sizes don't offer it. --}}
                <fieldset class="flex flex-col gap-2" data-extra-field>
                    <legend class="yc-mono mb-2 text-muted">Tambahan</legend>
                    <div class="flex flex-wrap gap-2">
                        <label class="block">
                            <input type="checkbox" name="extra_matcha" value="1" class="peer sr-only" data-extra-toggle @disabled($soldOut || ! $preExtra)>
                            <span class="yc-chip">
                                <span class="text-sm font-bold leading-[18px]">Extra matcha</span>
                                <span class="yc-chip-sub" data-extra-sub>{{ isset($extraPrices[$preSize]) ? (($preExtra) ? '+'.$rupiah($extraPrices[$preSize]) : 'Habis') : 'Tidak ada' }}</span>
                            </span>
                        </label>
                    </div>
                </fieldset>
            @endif
        </div>


        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
            <p class="text-xl font-extrabold leading-5 tracking-[-0.2px] text-charcoal" data-price-display aria-live="polite">{{ $rupiah($price) }}</p>

            <div class="flex items-center gap-2">
                {{-- Jumlah botol: − / + steppers around a number field (typing works too).
                     max follows what is left of the chosen size and sweetness. --}}
                <div class="yc-qty {{ $canAdd ? '' : 'hidden' }}" data-qty>
                    <button type="button" class="yc-qty-btn" data-qty-step="-1" aria-label="Kurangi jumlah {{ $product->name }}">&minus;</button>
                    <input
                        type="number"
                        name="qty"
                        value="1"
                        min="1"
                        max="{{ $preMax }}"
                        inputmode="numeric"
                        class="yc-qty-input"
                        aria-label="Jumlah {{ $product->name }}"
                    >
                    <button type="button" class="yc-qty-btn" data-qty-step="1" aria-label="Tambah jumlah {{ $product->name }}">+</button>
                </div>
                <button
                    type="submit"
                    data-add-button
                    @disabled(! $canAdd)
                    class="{{ $canAdd ? 'yc-btn-cta' : 'yc-btn cursor-not-allowed border-charcoal/30 bg-charcoal/10 text-charcoal/50' }}"
                >{{ $addLabel }}</button>
            </div>
        </div>
    </form>
</article>
