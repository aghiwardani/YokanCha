@extends('layouts.admin')

@section('title', 'Master Data - YokanCha')

@section('admin-content')
@php
    // After a failed submit, reopen the tab the form came from.
    $formOrigin = (string) old('_form');
    $serverTab = match (true) {
        str_starts_with($formOrigin, 'ingredient-') => 'bahan',
        str_starts_with($formOrigin, 'size-') => 'ukuran',
        str_starts_with($formOrigin, 'sweetness-') => 'manis',
        default => '',
    };

    $tabs = [
        'produk' => ['label' => 'Produk', 'count' => $products->count()],
        'bahan' => ['label' => 'Bahan', 'count' => $ingredients->count()],
        'ukuran' => ['label' => 'Ukuran', 'count' => $sizes->count()],
        'manis' => ['label' => 'Tingkat Manis', 'count' => $sweetnessLevels->count()],
    ];
@endphp
<div class="min-h-screen bg-oat px-4 py-10 sm:py-12">
    <div class="mx-auto max-w-5xl">
        <header class="mb-8">
            <h1 class="font-display text-4xl leading-tight text-charcoal">Master Data</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-muted">
                Daftar dasar yang dipakai di seluruh aplikasi: produk yang dijual, bahan untuk resep dan stok, ukuran botol, serta tingkat manis.
            </p>
        </header>

        @if (session('status'))
            <div role="status" class="mb-6 flex items-start gap-3 rounded-brand border border-brand/30 bg-sage-soft px-4 py-3 text-sm text-charcoal">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-brand" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div role="alert" class="mb-6 flex items-start gap-3 rounded-brand border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm-.75 4.5a.75.75 0 0 1 1.5 0v4a.75.75 0 0 1-1.5 0v-4ZM10 14.5a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <div role="tablist" aria-label="Jenis master data" class="mb-6 flex gap-1 border-b border-line">
            @foreach ($tabs as $id => $tab)
                <a
                    href="#{{ $id }}"
                    id="tab-{{ $id }}"
                    role="tab"
                    aria-controls="{{ $id }}"
                    data-tab="{{ $id }}"
                    class="-mb-px flex items-center gap-2 border-b-2 border-transparent px-4 py-3 text-sm font-bold text-muted transition-colors hover:text-charcoal focus:outline-none focus-visible:ring-2 focus-visible:ring-cta aria-selected:border-brand aria-selected:text-brand"
                >
                    {{ $tab['label'] }}
                    <span class="rounded-full bg-oat-deep px-2 py-0.5 text-xs font-bold text-muted">{{ $tab['count'] }}</span>
                </a>
            @endforeach
        </div>

        @include('admin.master._products')
        @include('admin.master._ingredients')
        @include('admin.master._sizes')
        @include('admin.master._sweetness')
    </div>
</div>

<script>
    // Tabs: one panel at a time, chosen by the URL hash (#produk, #bahan,
    // #ukuran) so redirects land on the right list. Without JS every panel shows.
    (function () {
        var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-tab]'));
        var ids = tabs.map(function (tab) { return tab.dataset.tab; });
        var serverTab = @json($serverTab);

        function show(id, updateHash) {
            tabs.forEach(function (tab) {
                var active = tab.dataset.tab === id;
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.tabIndex = active ? 0 : -1;
                document.getElementById(tab.dataset.tab).hidden = ! active;
            });
            if (updateHash && history.replaceState) {
                history.replaceState(null, '', '#' + id);
            }
        }

        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function (event) {
                event.preventDefault();
                show(tab.dataset.tab, true);
            });
            tab.addEventListener('keydown', function (event) {
                var step = event.key === 'ArrowRight' ? 1 : (event.key === 'ArrowLeft' ? -1 : 0);
                if (! step) { return; }
                var next = tabs[(index + step + tabs.length) % tabs.length];
                next.focus();
                show(next.dataset.tab, true);
            });
        });

        var fromHash = location.hash.slice(1);
        show(serverTab || (ids.indexOf(fromHash) !== -1 ? fromHash : ids[0]), false);
    })();
</script>
@endsection
