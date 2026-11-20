{{--
    Read-only menu card shown while no PO is open: same look as menu._card,
    but no form, so nothing can be added to the cart.
    $product: an active Product.
--}}
@php
    $sizes = $product->sizesSmallestFirst();
    $sweetness = array_keys($product->sweetnessLeastFirst());
    $firstSize = $sizes[0] ?? null;
    $rupiah = fn (int $amount) => 'Rp'.number_format($amount, 0, ',', '.');
    $toneClass = [
        'amber' => 'bg-amber-soft',
        'sage' => 'bg-sage-soft',
        'oat' => 'bg-oat-deep',
    ][$product->tone] ?? 'bg-sage-soft';
@endphp

<article class="flex h-full flex-col overflow-hidden rounded-card border border-charcoal bg-surface" data-catalog-card>
    <div class="relative flex h-[220px] shrink-0 flex-col items-center justify-end overflow-hidden border-b border-charcoal pb-[25px] lg:h-[290px] lg:pb-[18px] {{ $toneClass }}">
        <img src="{{ asset('images/landing/bottle/circle.svg') }}" alt="" class="absolute left-1/2 top-[45px] h-[190px] w-[190px] max-w-none -translate-x-1/2 lg:top-[98px] lg:h-[240px] lg:w-[240px]">
        <x-bottle :style="$product->bottle_style" size="sm" :label="$firstSize ?? '250 ml'" class="origin-bottom scale-[0.8947] lg:scale-100" />

        @if ($product->tag)
            <span class="absolute left-3 top-3 lg:left-[14px] lg:top-[14px] yc-tag-amber">{{ $product->tag }}</span>
        @endif
    </div>

    <div class="flex flex-1 flex-col justify-between gap-3 p-[18px] lg:p-5">
        <div class="flex flex-col gap-3 lg:gap-[14px]">
            <h3 class="font-display text-[25px] leading-7 text-charcoal">{{ $product->name }}</h3>

            @if ($product->description)
                <p class="text-sm leading-[22px] text-muted">{{ $product->description }}</p>
            @endif

            @if (! empty($sizes))
                <div class="flex flex-col gap-2">
                    <p class="yc-mono text-muted">Ukuran</p>
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($sizes as $size)
                            <li class="yc-chip">
                                <span class="text-sm font-bold leading-[18px]">{{ $size }}</span>
                                <span class="yc-chip-sub">{{ $rupiah($product->priceFor($size)) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($sweetness))
                <div class="flex flex-col gap-2">
                    <p class="yc-mono text-muted">Manis</p>
                    <p class="text-sm text-charcoal">{{ implode(', ', $sweetness) }}</p>
                </div>
            @endif
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
            <p class="text-xl font-extrabold leading-5 tracking-[-0.2px] text-charcoal">
                @if (count($sizes) > 1)<span class="text-sm font-semibold text-muted">mulai</span> @endif{{ $rupiah($product->priceFor($firstSize)) }}
            </p>
            <span class="yc-btn cursor-default border-charcoal/30 bg-charcoal/10 text-charcoal/60">Pesan saat PO buka</span>
        </div>
    </div>
</article>
