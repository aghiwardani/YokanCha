@extends('layouts.admin')

@section('title', 'Pesanan - YokanCha')

@php
    $rp = fn (int $n) => 'Rp'.number_format($n, 0, ',', '.');

    $statusLabels = [
        'new' => 'Baru',
        'diproses' => 'Diproses',
        'siap_diambil' => 'Siap diambil',
        'selesai' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    $statusStyles = [
        'new' => 'bg-charcoal text-surface',
        'diproses' => 'bg-cta text-surface',
        'siap_diambil' => 'bg-brand text-surface',
        'selesai' => 'bg-sage-soft text-brand',
        'cancelled' => 'bg-oat-deep text-muted line-through',
    ];

    $chip = fn (bool $active) => $active
        ? 'border-charcoal bg-charcoal text-surface'
        : 'border-line bg-surface text-charcoal hover:border-charcoal';
@endphp

@section('admin-content')
<div class="min-h-screen bg-oat py-10 px-4">
    <div class="max-w-5xl mx-auto space-y-6" data-orders-root>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-3xl font-bold text-charcoal">Pesanan</h1>
                    @if ($periods->isNotEmpty())
                        <a href="{{ route('admin.orders.create', array_filter(['period' => $selectedPeriodId])) }}"
                           class="rounded-full bg-cta px-4 py-2 text-sm font-semibold text-surface hover:bg-opacity-90">+ Tambah pesanan</a>
                    @endif
                </div>
                <p class="mt-1 text-sm text-muted">
                    {{ $selectedPeriodId ? optional($periods->firstWhere('id', (int) $selectedPeriodId))->name : 'Semua periode' }}
                </p>
            </div>

            <form method="GET" action="{{ route('admin.orders') }}" class="flex flex-wrap items-end gap-3">
                @if ($payment)<input type="hidden" name="payment" value="{{ $payment }}">@endif
                @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                <div>
                    <label for="po_period_id" class="block text-xs font-semibold text-muted mb-1">Periode</label>
                    <select
                        id="po_period_id"
                        name="po_period_id"
                        class="px-3 py-2 bg-surface border border-line rounded-brand text-sm focus:outline-none focus:ring-2 focus:ring-cta"
                        onchange="this.form.submit()"
                    >
                        <option value="">Semua Periode</option>
                        @foreach ($periods as $period)
                            <option value="{{ $period->id }}" @selected((string) $selectedPeriodId === (string) $period->id)>
                                {{ $period->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="q" class="block text-xs font-semibold text-muted mb-1">Cari</label>
                    <input
                        id="q"
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        placeholder="Nama, nomor WA, atau kode"
                        class="w-64 px-3 py-2 bg-surface border border-line rounded-brand text-sm focus:outline-none focus:ring-2 focus:ring-cta"
                    >
                </div>
            </form>
        </div>

        @if ($selectedPeriodId && $periods->firstWhere('id', (int) $selectedPeriodId))
            <a href="{{ route('admin.periods.production', (int) $selectedPeriodId) }}"
               class="flex items-center justify-between gap-4 p-4 rounded-brand bg-brand text-surface hover:opacity-95">
                <span>
                    <span class="block font-bold">Lembar produksi</span>
                    <span class="block text-sm opacity-80">Jumlah botol per varian, resep, total bahan, dan daftar ambil untuk batch ini.</span>
                </span>
                <span class="shrink-0 px-4 py-2 rounded-full bg-surface text-sm font-semibold text-brand">Buka</span>
            </a>
        @endif

        @if (session('status'))
            <div class="p-4 bg-sage-soft border border-brand/30 rounded-brand text-charcoal text-sm">
                {{ session('status') }}
            </div>
        @endif

        {{-- Summary for the chosen period (cancelled orders don't count). --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-surface rounded-brand border border-line p-4">
                <p class="text-xs font-semibold text-muted">Pesanan aktif</p>
                <p class="mt-1 text-2xl font-bold text-charcoal">{{ $summary['orders'] }}</p>
                <p class="text-xs text-muted">{{ $summary['bottles'] }} botol</p>
            </div>
            <div class="bg-surface rounded-brand border border-line p-4">
                <p class="text-xs font-semibold text-muted">Total penjualan</p>
                <p class="mt-1 text-2xl font-bold text-charcoal">{{ $rp($summary['revenue']) }}</p>
            </div>
            <div class="bg-sage-soft rounded-brand border border-brand/20 p-4">
                <p class="text-xs font-semibold text-brand">Sudah dibayar</p>
                <p class="mt-1 text-2xl font-bold text-brand">{{ $rp($summary['paid']) }}</p>
            </div>
            <div class="bg-amber-soft rounded-brand border border-cta/20 p-4">
                <p class="text-xs font-semibold text-amber-text">Belum dibayar</p>
                <p class="mt-1 text-2xl font-bold text-amber-text">{{ $rp($summary['unpaid']) }}</p>
                <p class="text-xs text-amber-text">{{ $summary['unpaidCount'] }} pesanan</p>
            </div>
        </div>

        {{-- Quick filters --}}
        <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <span class="w-20 text-xs font-semibold text-muted">Bayar</span>
                @foreach ([null => 'Semua', 'unpaid' => 'Belum bayar', 'paid' => 'Sudah bayar'] as $value => $label)
                    <a href="{{ request()->fullUrlWithQuery(['payment' => $value ?: null]) }}"
                       class="px-3 py-1 rounded-full border text-xs font-semibold transition {{ $chip(($payment ?: '') === (string) $value) }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="w-20 text-xs font-semibold text-muted">Status</span>
                <a href="{{ request()->fullUrlWithQuery(['status' => null]) }}"
                   class="px-3 py-1 rounded-full border text-xs font-semibold transition {{ $chip(! $status) }}">
                    Semua
                </a>
                @foreach ($statusLabels as $value => $label)
                    <a href="{{ request()->fullUrlWithQuery(['status' => $value]) }}"
                       class="px-3 py-1 rounded-full border text-xs font-semibold transition {{ $chip($status === $value) }}">
                        {{ $label }} <span class="opacity-60">{{ $statusCounts[$value] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        @if ($orders->isEmpty())
            <div class="bg-surface rounded-brand border border-line p-8 text-center">
                <p class="text-charcoal">Tidak ada pesanan yang cocok.</p>
                @if ($payment || $status || $search)
                    <a href="{{ route('admin.orders', array_filter(['po_period_id' => $selectedPeriodId])) }}" class="mt-2 inline-block text-sm font-semibold text-brand underline">
                        Hapus filter
                    </a>
                @endif
            </div>
        @else
            <p class="text-xs text-muted">Menampilkan {{ $orders->count() }} pesanan, terbaru di atas.</p>

            <div class="space-y-3">
                @foreach ($orders as $order)
                    @php
                        $paid = $order->payment_status === 'paid';
                        $cancelled = $order->order_status === 'cancelled';
                    @endphp
                    <article id="order-{{ $order->id }}" class="relative scroll-mt-6 bg-surface rounded-brand border border-line overflow-hidden {{ $cancelled ? 'opacity-60' : '' }}">
                        <div class="absolute inset-y-0 left-0 w-1.5 {{ $cancelled ? 'bg-line' : ($paid ? 'bg-brand' : 'bg-cta') }}" aria-hidden="true"></div>

                        <div class="pl-6 pr-5 py-4 flex flex-col md:flex-row md:items-start gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                                    <span class="font-mono font-semibold text-charcoal">{{ $order->order_number }}</span>
                                    @if ($order->source === 'admin')
                                        <span class="rounded-full bg-oat-deep px-2 py-0.5 font-semibold text-charcoal" title="Ditambahkan lewat admin">Manual</span>
                                    @endif
                                    <span>{{ $order->created_at?->timezone(config('app.timezone'))->translatedFormat('j M, H.i') }}</span>
                                    @unless ($selectedPeriodId)
                                        <span>{{ optional($order->poPeriod)->name }}</span>
                                    @endunless
                                </div>

                                <a href="{{ route('admin.orders.show', $order) }}" class="mt-1 block text-lg font-bold text-charcoal hover:underline">
                                    {{ $order->customer_name }}
                                </a>

                                <ul class="mt-2 space-y-0.5 text-sm text-charcoal">
                                    @foreach ($order->items as $item)
                                        <li>
                                            <span class="font-semibold">{{ $item->qty }}×</span>
                                            {{ optional($item->product)->name ?? 'Produk dihapus' }}
                                            @php $variant = $item->variantLabel(); @endphp
                                            @if ($variant)
                                                <span class="text-muted">({{ $variant }})</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="flex md:flex-col md:items-end items-center justify-between gap-3 shrink-0">
                                <p class="text-xl font-bold text-charcoal">{{ $rp($order->total()) }}</p>
                                @if ($cancelled)
                                    <div class="flex gap-2">
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $paid ? 'bg-sage-soft text-brand' : 'bg-amber-soft text-amber-text' }}">
                                            {{ $paid ? 'Sudah Bayar' : 'Belum Bayar' }}
                                        </span>
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $statusStyles['cancelled'] }}">Dibatalkan</span>
                                    </div>
                                @endif
                                <div class="flex gap-2">
                                    <a href="https://wa.me/{{ \App\Support\Phone::normalize($order->customer_whatsapp) }}" target="_blank" rel="noopener"
                                       class="px-3 py-1.5 rounded-full border border-brand text-xs font-semibold text-brand hover:bg-sage-soft">
                                        Chat WA {{ $order->customer_whatsapp }}
                                    </a>
                                    <a href="{{ route('admin.orders.show', $order) }}"
                                       class="px-3 py-1.5 rounded-full bg-charcoal text-xs font-semibold text-surface hover:opacity-90">
                                        Detail
                                    </a>
                                </div>
                            </div>
                        </div>

                        @unless ($cancelled)
                            {{-- Quick changes: one click, back to this card with the filters kept. --}}
                            <div class="border-t border-line bg-oat/60 pl-6 pr-5 py-3 flex flex-wrap items-center gap-x-6 gap-y-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-muted">Bayar</span>
                                    <div class="inline-flex rounded-full border border-line bg-surface p-0.5">
                                        <form action="{{ route('admin.orders.mark-unpaid', $order) }}" method="POST" data-quick>
                                            @csrf
                                            <input type="hidden" name="from" value="list">
                                            <button type="submit" @disabled(! $paid) aria-pressed="{{ $paid ? 'false' : 'true' }}"
                                                class="px-3 py-1 rounded-full text-xs font-semibold transition {{ $paid ? 'text-muted hover:bg-oat' : 'bg-amber-soft text-amber-text cursor-default' }}">
                                                Belum bayar
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.orders.mark-paid', $order) }}" method="POST" data-quick>
                                            @csrf
                                            <input type="hidden" name="from" value="list">
                                            <button type="submit" @disabled($paid) aria-pressed="{{ $paid ? 'true' : 'false' }}"
                                                class="px-3 py-1 rounded-full text-xs font-semibold transition {{ $paid ? 'bg-brand text-surface cursor-default' : 'text-muted hover:bg-oat' }}">
                                                Sudah bayar
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <form action="{{ route('admin.orders.status', $order) }}" method="POST" class="flex items-center gap-2" data-quick>
                                    @csrf
                                    <input type="hidden" name="from" value="list">
                                    <span class="text-xs font-semibold text-muted">Status</span>
                                    <div class="inline-flex flex-wrap rounded-full border border-line bg-surface p-0.5">
                                        @foreach (['new', 'diproses', 'siap_diambil', 'selesai'] as $value)
                                            @php $active = $order->order_status === $value; @endphp
                                            <button type="submit" name="order_status" value="{{ $value }}" @disabled($active) aria-pressed="{{ $active ? 'true' : 'false' }}"
                                                class="px-3 py-1 rounded-full text-xs font-semibold transition {{ $active ? $statusStyles[$value].' cursor-default' : 'text-muted hover:bg-oat' }}">
                                                {{ $statusLabels[$value] }}
                                            </button>
                                        @endforeach
                                    </div>
                                </form>
                            </div>
                        @endunless
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</div>

<div data-quick-toast role="status" aria-live="polite"
     class="pointer-events-none fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-full bg-charcoal px-5 py-3 text-sm font-semibold text-surface shadow-lg opacity-0 transition-opacity duration-200"></div>

<script>
    // Quick payment/status buttons: save in the background, then swap in a
    // fresh copy of the list so counts and filters stay right and the page
    // does not jump. Without JavaScript the forms still work normally.
    (() => {
        const toast = document.querySelector('[data-quick-toast]');
        let timer;
        const say = (text) => {
            toast.textContent = text;
            toast.classList.remove('opacity-0');
            clearTimeout(timer);
            timer = setTimeout(() => toast.classList.add('opacity-0'), 2500);
        };

        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('form[data-quick]');
            if (!form) return;
            event.preventDefault();

            const data = new FormData(form);
            if (event.submitter?.name) data.append(event.submitter.name, event.submitter.value);
            form.querySelectorAll('button').forEach((b) => { b.disabled = true; });

            try {
                const saved = await fetch(form.action, {
                    method: 'POST',
                    body: data,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const body = await saved.json().catch(() => ({}));
                if (!saved.ok) {
                    const firstError = body.errors ? Object.values(body.errors)[0][0] : null;
                    throw new Error(firstError || (saved.status === 419 ? 'Sesi habis. Muat ulang halaman.' : 'Gagal menyimpan. Coba lagi.'));
                }

                const page = await fetch(location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const fresh = new DOMParser().parseFromString(await page.text(), 'text/html').querySelector('[data-orders-root]');
                if (fresh) document.querySelector('[data-orders-root]').replaceWith(fresh);
                say(body.message || 'Tersimpan.');
            } catch (error) {
                form.querySelectorAll('button:not([aria-pressed="true"])').forEach((b) => { b.disabled = false; });
                say(error.message);
            }
        });
    })();
</script>
@endsection
