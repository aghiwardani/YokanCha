@extends('layouts.admin')

@section('title', 'Laporan - YokanCha')

@php
    $rp = fn ($n) => ($n < 0 ? '-' : '').'Rp'.number_format(abs(round($n)), 0, ',', '.');
    $statusLabel = ['open' => 'Buka', 'closed' => 'Tutup', 'draft' => 'Draf'];
    $maxRevenue = max(1, collect($rows)->max(fn ($r) => $r['profit']['revenue']) ?? 1);
    $tone = fn ($n) => $n >= 0 ? 'text-brand' : 'text-amber-text';
@endphp

@section('admin-content')
<div class="min-h-screen bg-oat py-8 px-4 sm:py-10">
    <div class="max-w-5xl mx-auto space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-charcoal">Laporan</h1>
                <p class="mt-1 text-sm text-muted">Rekap penjualan, HPP (COGS), dan untung setiap periode. Pesanan yang dibatalkan tidak dihitung.</p>
            </div>
            @if ($rows)
                <a href="{{ route('admin.reports.csv') }}" class="px-4 py-2 rounded-full border border-brand bg-surface text-sm font-semibold text-brand hover:bg-sage-soft">
                    Unduh CSV (Excel)
                </a>
            @endif
        </div>

        @if (! $rows)
            <div class="bg-surface rounded-brand border border-line p-8 text-center text-charcoal">
                Belum ada periode. Laporan muncul setelah kamu membuat periode PO.
            </div>
        @else
            {{-- All periods together --}}
            <section class="space-y-2">
                <h2 class="text-sm font-bold text-muted">Semua periode ({{ $total['periods'] }})</h2>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-surface rounded-brand border border-line p-4">
                        <p class="text-xs font-semibold text-muted">Penjualan</p>
                        <p class="mt-1 text-xl font-bold text-charcoal sm:text-2xl">{{ $rp($total['revenue']) }}</p>
                        <p class="text-xs text-muted">{{ $total['orders'] }} pesanan, {{ $total['bottles'] }} botol</p>
                    </div>
                    <div class="bg-surface rounded-brand border border-line p-4">
                        <p class="text-xs font-semibold text-muted">HPP (COGS)</p>
                        <p class="mt-1 text-xl font-bold text-charcoal sm:text-2xl">{{ $rp($total['cogs']) }}</p>
                    </div>
                    <div class="bg-sage-soft rounded-brand border border-brand/20 p-4">
                        <p class="text-xs font-semibold text-brand">Untung kotor</p>
                        <p class="mt-1 text-xl font-bold sm:text-2xl {{ $tone($total['gross']) }}">{{ $rp($total['gross']) }}</p>
                        <p class="text-xs text-brand">Margin {{ $total['margin'] }}%</p>
                    </div>
                    <div class="bg-surface rounded-brand border border-line p-4">
                        <p class="text-xs font-semibold text-muted">Untung bersih</p>
                        <p class="mt-1 text-xl font-bold sm:text-2xl {{ $tone($total['cash']) }}">{{ $rp($total['cash']) }}</p>
                        @if ($total['unpaid'] > 0)
                            <p class="text-xs text-amber-text">{{ $rp($total['unpaid']) }} belum dibayar</p>
                        @endif
                    </div>
                </div>
            </section>

            {{-- One card per period, newest first --}}
            <section class="space-y-3">
                <h2 class="text-sm font-bold text-muted">Per periode, terbaru di atas</h2>

                @foreach ($rows as $row)
                    @php $p = $row['profit']; $period = $row['period']; @endphp
                    <article class="bg-surface rounded-brand border border-line p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <a href="{{ route('admin.reports.show', $period) }}" class="text-lg font-bold text-charcoal hover:underline">{{ $period->name }}</a>
                                <p class="text-xs text-muted">
                                    {{ $period->opens_at?->translatedFormat('j M Y') ?? '-' }} sampai {{ $period->closes_at?->translatedFormat('j M Y') ?? '-' }}
                                    <span class="ml-1 rounded-full bg-oat px-2 py-0.5 font-semibold text-charcoal">{{ $statusLabel[$period->status] ?? $period->status }}</span>
                                </p>
                            </div>
                            <a href="{{ route('admin.reports.show', $period) }}" class="px-3 py-1.5 rounded-full bg-charcoal text-xs font-semibold text-surface hover:opacity-90">Lihat laporan</a>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3 lg:grid-cols-6">
                            <div>
                                <dt class="text-xs text-muted">Pesanan</dt>
                                <dd class="font-bold text-charcoal">{{ $row['orders'] }} <span class="text-sm font-normal text-muted">/ {{ $row['bottles'] }} botol</span></dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted">Penjualan</dt>
                                <dd class="font-bold text-charcoal">{{ $rp($p['revenue']) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted">Belum dibayar</dt>
                                <dd class="font-bold {{ $p['unpaid'] > 0 ? 'text-amber-text' : 'text-charcoal' }}">{{ $rp($p['unpaid']) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted">HPP (COGS)</dt>
                                <dd class="font-bold text-charcoal">{{ $rp($p['cogs']) }}@unless ($p['cogsComplete'])<span class="text-amber-text" title="Ada harga beli bahan yang belum diisi">*</span>@endunless</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted">Untung kotor</dt>
                                <dd class="font-bold {{ $tone($p['gross']) }}">{{ $rp($p['gross']) }} <span class="text-xs font-normal text-muted">{{ $p['margin'] }}%</span></dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted">Untung bersih</dt>
                                <dd class="font-bold {{ $tone($p['cash']) }}">{{ $rp($p['cash']) }}</dd>
                            </div>
                        </dl>

                        {{-- Sales compared with the best period --}}
                        <div class="mt-4 h-1.5 rounded-full bg-oat" aria-hidden="true">
                            <div class="h-1.5 rounded-full bg-brand" style="width: {{ round($p['revenue'] / $maxRevenue * 100) }}%"></div>
                        </div>

                        <p class="mt-3 text-xs text-muted">
                            @if ($row['best'])
                                Terlaris: <span class="font-semibold text-charcoal">{{ $row['best'] }}</span> ({{ $row['bestBottles'] }} botol)
                            @else
                                Belum ada pesanan.
                            @endif
                            @if ($row['cancelled'])
                                · {{ $row['cancelled'] }} pesanan dibatalkan
                            @endif
                            @unless ($p['cogsComplete'])
                                · <span class="text-amber-text">* harga beli bahan belum lengkap</span>
                            @endunless
                        </p>
                    </article>
                @endforeach
            </section>
        @endif
    </div>
</div>
@endsection
