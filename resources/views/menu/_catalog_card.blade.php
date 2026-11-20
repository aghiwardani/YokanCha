{{--
    Menu card shown while no PO is open. Looks and behaves like menu._card
    (size and sweetness can be picked, the price follows the size), but the
    quantity and add button are switched off, so nothing reaches the cart.
    $product: an active Product.
--}}
@php
    $sizes = $product->sizesSmallestFirst();
    $sweetnessPercents = $product->sweetnessLeastFirst();
    $sweetness = array_keys($sweetnessPercents);
    // Same preselection as the order card: smallest size, sweetness closest to Normal.
    $preSweet = collect($sweetness)->sortBy(fn ($level) => [abs($sweetnessPercents[$level] - 100), $sweetnessPercents[$level]])->first();
    $firstSize = $sizes[0] ?? null;
    $rupiah = fn (int $amount) => 'Rp'.number_format($amount, 0, ',', '.');
    $toneClass = [
        'amber' => 'bg-amber-soft',
        'sage' => 'bg-sage-soft',
        'oat' => 'bg-oat-deep',
    ][$product->tone] ?? 'bg-sage-soft';
@endphp

<article class="flex h-full flex-col overflow-hidden rounded-card border border-charcoal bg-surface" data-catalog-card>
    {{-- Foto --}}
    <div class="relative flex h-[220px] shrink-0 flex-col items-center justify-end overflow-hidden border-b border-charcoal pb-[25px] lg:h-[290px] lg:pb-[18px] {{ $toneClass }}">
        <img src="{{ asset('images/landing/bottle/circle.svg') }}" alt="" class="absolute left-1/2 top-[45px] h-[190px] w-[190px] max-w-none -translate-x-1/2 lg:top-[98px] lg:h-[240px] lg:w-[240px]">
        <x-bottle :style="$product->bottle_style" size="sm" :label="$firstSize ?? '250 ml'" class="origin-bottom scale-[0.8947] lg:scale-100" />

        @if ($product->tag)
            <span class="absolute left-3 top-3 lg:left-[14px] lg:top-[14px] yc-tag-amber">{{ $product->tag }}</span>
        @endif
    </div>

    {{-- Isi. A form only so each card's radio buttons group on their own; it never submits. --}}
    <form class="flex flex-1 flex-col justify-between gap-3 p-[18px] lg:p-5" onsubmit="return false;">
        <div class="flex flex-col gap-3 lg:gap-[14px]">
            <h3 class="font-display text-[25px] leading-7 text-charcoal">{{ $product->name }}</h3>

            @if ($product->description)
                <p class="text-sm leading-[22px] text-muted">{{ $product->description }}</p>
            @endif

            @if (! empty($sizes))
                <fieldset class="flex flex-col gap-2">
                    <legend class="yc-mono mb-2 text-muted">Ukuran</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($sizes as $size)
                            <label class="block">
                                <input
                                    type="radio"
                                    name="size"
                                    value="{{ $size }}"
                                    class="peer sr-only"
                                    data-price="{{ $rupiah($product->priceFor($size)) }}"
                                    @checked($size === $firstSize)
                                >
                                <span class="yc-chip">
                                    <span class="text-sm font-bold leading-[18px]">{{ $size }}</span>
                                    <span class="yc-chip-sub">{{ $rupiah($product->priceFor($size)) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            @if (! empty($sweetness))
                <fieldset class="flex flex-col gap-2">
                    <legend class="yc-mono mb-2 text-muted">Manis</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($sweetness as $level)
                            <label class="block">
                                <input type="radio" name="sweetness" value="{{ $level }}" class="peer sr-only" @checked($level === $preSweet)>
                                <span class="yc-chip">
                                    <span class="text-sm font-bold leading-[18px]">{{ $level }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
            <p class="text-xl font-extrabold leading-5 tracking-[-0.2px] text-charcoal" data-price-display aria-live="polite">{{ $rupiah($product->priceFor($firstSize)) }}</p>

            <div class="flex items-center gap-2">
                <div class="yc-qty opacity-50" aria-hidden="true">
                    <button type="button" class="yc-qty-btn" disabled tabindex="-1">&minus;</button>
                    <input type="number" value="1" disabled tabindex="-1" class="yc-qty-input">
                    <button type="button" class="yc-qty-btn" disabled tabindex="-1">+</button>
                </div>
                <button type="button" disabled class="yc-btn cursor-not-allowed border-charcoal/30 bg-charcoal/10 text-charcoal/50">Pesan saat PO buka</button>
            </div>
        </div>
    </form>
</article>
