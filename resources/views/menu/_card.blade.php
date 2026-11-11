{{--
    Menu product card (Figma "Kartu/*", desktop 7:13 & 7:61, mobile 9:85).
    $item: PoPeriodProduct with ->product loaded. Every card has the same layout and
    size so the grid stays even (the wide Figma "featured" card is not used).
--}}
@php
    $product = $item->product;
    $remaining = $item->remaining();
    $soldOut = $remaining <= 0;
    $sizes = $product->size_options ?: [];
    $sweetness = $product->sweetness_options ?: [];
    $firstSize = $sizes[0] ?? null;
    $price = $product->priceFor($firstSize);
    $rupiah = fn (int $amount) => 'Rp'.number_format($amount, 0, ',', '.');

    $tag = $soldOut ? 'Habis' : ($product->tag ?: ($remaining <= 5 ? 'Stok terbatas' : null));

    $toneClass = [
        'amber' => 'bg-amber-soft',
        'sage' => 'bg-sage-soft',
        'oat' => 'bg-oat-deep',
    ][$product->tone] ?? 'bg-sage-soft';

    $bottleLabel = $firstSize ?? '250 ml';
@endphp

<article class="flex h-full flex-col overflow-hidden rounded-card border border-charcoal bg-surface" data-product-card>
    {{-- Foto --}}
    <div class="relative flex h-[220px] shrink-0 flex-col items-center justify-end overflow-hidden border-b border-charcoal pb-[25px] lg:h-[290px] lg:pb-[18px] {{ $toneClass }}">
        <img src="{{ asset('images/landing/bottle/circle.svg') }}" alt="" class="absolute left-1/2 top-[45px] h-[190px] w-[190px] max-w-none -translate-x-1/2 lg:top-[98px] lg:h-[240px] lg:w-[240px]">
        <x-bottle :style="$product->bottle_style" size="sm" :label="$bottleLabel" class="origin-bottom scale-[0.8947] lg:scale-100" />

        @if ($tag)
            <span class="absolute left-3 top-3 lg:left-[14px] lg:top-[14px] {{ $soldOut ? 'yc-tag' : 'yc-tag-amber' }}">{{ $tag }}</span>
        @endif
    </div>

    {{-- Isi --}}
    <form method="POST" action="{{ route('cart.add') }}" data-cart-add class="flex flex-1 flex-col justify-between gap-3 p-[18px] lg:p-5">
        @csrf
        <input type="hidden" name="po_period_product_id" value="{{ $item->id }}">
        <input type="hidden" name="qty" value="1">

        <div class="flex flex-col gap-3 lg:gap-[14px]">
            <h3 class="font-display text-[25px] leading-7 text-charcoal">{{ $product->name }}</h3>

            @if ($product->description)
                <p class="text-sm leading-[22px] text-muted">{{ $product->description }}</p>
            @endif

            <p class="sr-only">Sisa {{ max(0, $remaining) }} botol untuk batch ini.</p>

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
                                    @checked($index === 0)
                                    @disabled($soldOut)
                                >
                                <span class="yc-chip">
                                    <span class="text-sm font-bold leading-[18px]">{{ $size }}</span>
                                    <span class="yc-chip-sub">{{ $rupiah($product->priceFor($size)) }}</span>
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
                                    @checked($index === 0)
                                    @disabled($soldOut)
                                >
                                <span class="yc-chip">
                                    <span class="text-sm font-bold leading-[18px]">{{ $level }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @else
                <input type="hidden" name="sweetness" value="-">
            @endif
        </div>

        <div class="flex items-center justify-between border-t border-line pt-4">
            <p class="text-xl font-extrabold leading-5 tracking-[-0.2px] text-charcoal" data-price-display aria-live="polite">{{ $rupiah($price) }}</p>

            @if ($soldOut)
                <button type="button" disabled class="yc-btn cursor-not-allowed border-charcoal/30 bg-charcoal/10 text-charcoal/50">Habis</button>
            @else
                <button type="submit" class="yc-btn-cta">Tambah</button>
            @endif
        </div>
    </form>
</article>
