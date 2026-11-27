{{--
    Menu tabs (Matcha / Coffee). $groups: category key => items in it.
    Each tab is a link (?kategori=coffee#menu) so it works without JavaScript;
    app.js switches the panels in place instead of reloading.
--}}
@if ($showTabs)
    <div class="flex flex-wrap items-center gap-2" role="tablist" aria-label="Kategori menu" data-menu-tabs>
        @foreach ($tabs as $tab)
            @php $active = $tab === $activeTab; @endphp
            <a
                href="{{ route('menu.index', ['kategori' => $tab]) }}#menu"
                id="menu-tab-{{ $tab }}"
                role="tab"
                aria-controls="menu-panel-{{ $tab }}"
                aria-selected="{{ $active ? 'true' : 'false' }}"
                data-menu-tab="{{ $tab }}"
                class="yc-menu-tab"
            >
                {{ App\Models\Product::CATEGORIES[$tab] }}
                <span class="yc-menu-tab-count">{{ count($groups[$tab] ?? []) }}</span>
            </a>
        @endforeach
    </div>
@endif
