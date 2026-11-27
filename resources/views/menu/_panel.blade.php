{{--
    One menu tab's cards. $category: tab key; $cards: its products (catalog) or
    period items; $card: the card view to render each with.
--}}
@php
    $label = App\Models\Product::CATEGORIES[$category];
@endphp
<div
    class="{{ $category !== $activeTab ? 'hidden' : '' }}"
    data-menu-panel="{{ $category }}"
    @if ($showTabs)
        role="tabpanel"
        id="menu-panel-{{ $category }}"
        aria-labelledby="menu-tab-{{ $category }}"
    @endif
>
    @if ($cards->isEmpty())
        <div class="flex items-center gap-4 rounded-card border border-dashed border-charcoal/40 bg-surface px-5 py-8 lg:px-6">
            <x-mascot name="lean" sizes="60px" class="yc-mascot h-[64px] w-auto shrink-0" />
            <div>
                <p class="font-display text-[22px] leading-7 text-charcoal">{{ $period ? "Menu {$label} belum ada di batch ini." : "Menu {$label} belum ada." }}</p>
                <p class="text-sm leading-[22px] text-muted">Lihat dulu menu di tab lainnya.</p>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($cards as $entry)
                @if ($card === 'menu._card')
                    @include('menu._card', ['item' => $entry, 'availability' => $availability])
                @else
                    @include('menu._catalog_card', ['product' => $entry])
                @endif
            @endforeach
        </div>
    @endif
</div>
