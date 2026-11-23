@extends('layouts.admin')

@section('title', 'Laporan '.$period->name.' - YokanCha')

@php
    $rp = fn ($n) => ($n < 0 ? '-' : '').'Rp'.number_format(abs(round($n)), 0, ',', '.');
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $tone = fn ($n) => $n >= 0 ? 'text-brand' : 'text-amber-text';
    $p = $profit;
    $share = fn ($bottlesOf) => $bottles > 0 ? round($bottlesOf / $bottles * 100) : 0;
    $th = 'px-3 py-2 text-xs font-semibold text-muted';
    $td = 'px-3 py-2.5';
@endphp

@section('admin-content')
<div class="min-h-screen bg-oat py-8 px-4 sm:py-10 print:bg-white print:py-0">
    <div class="max-w-5xl mx-auto space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <a href="{{ route('admin.reports') }}" class="text-sm font-semibold text-brand underline print:hidden">Semua laporan</a>
                <h1 class="mt-2 text-3xl font-bold text-charcoal">Laporan {{ $period->name }}</h1>
                <p class="mt-1 text-sm text-muted">
                    {{ $period->opens_at?->translatedFormat('j M Y, H.i') ?? '-' }} sampai {{ $period->closes_at?->translatedFormat('j M Y, H.i') ?? '-' }}.
                    {{ $orders->count() }} pesanan, {{ $bottles }} botol{{ $cancelledCount ? ", {$cancelledCount} dibatalkan (tidak dihitung)" : '' }}.
                </p>
            </div>
            <div class="flex flex-wrap gap-2 print:hidden">
                <a href="{{ route('admin.periods.production', $period) }}" class="px-4 py-2 rounded-full border border-brand bg-surface text-sm font-semibold text-brand hover:bg-sage-soft">Produksi</a>
                <button type="button" onclick="window.print()" class="px-4 py-2 rounded-full bg-charcoal text-sm font-semibold text-surface hover:opacity-90">Cetak</button>
            </div>
        </div>

        @if ($p['missingPrices'])
            <div class="p-4 rounded-brand border border-cta/30 bg-amber-soft text-sm text-amber-text print:hidden">
                Harga beli belum diisi untuk: {{ implode(', ', $p['missingPrices']) }}. HPP dan untung belum menghitung bahan itu.
                <a href="{{ route('admin.periods.stock', $period) }}" class="font-semibold underline">Isi di Stok bahan</a>
            </div>
        @endif

        @if ($orders->isEmpty())
            <div class="bg-surface rounded-brand border border-line p-8 text-center text-charcoal">Belum ada pesanan di periode ini.</div>
        @else
            {{-- Ringkasan --}}
            <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-surface rounded-brand border border-line p-4">
                    <p class="text-xs font-semibold text-muted">Penjualan</p>
                    <p class="mt-1 text-xl font-bold text-charcoal sm:text-2xl">{{ $rp($p['revenue']) }}</p>
                    <p class="text-xs text-muted">Rata-rata {{ $rp($p['revenue'] / max(1, $orders->count())) }} per pesanan</p>
                </div>
                <div class="bg-surface rounded-brand border border-line p-4">
                    <p class="text-xs font-semibold text-muted">Pembayaran</p>
                    <p class="mt-1 text-xl font-bold text-brand sm:text-2xl">{{ $rp($p['paid']) }}</p>
                    <p class="text-xs {{ $p['unpaid'] > 0 ? 'text-amber-text' : 'text-muted' }}">{{ $p['unpaid'] > 0 ? $rp($p['unpaid']).' belum dibayar' : 'Semua sudah dibayar' }}</p>
                </div>
                <div class="bg-sage-soft rounded-brand border border-brand/20 p-4">
                    <p class="text-xs font-semibold text-brand">Untung kotor</p>
                    <p class="mt-1 text-xl font-bold sm:text-2xl {{ $tone($p['gross']) }}">{{ $rp($p['gross']) }}</p>
                    <p class="text-xs text-brand">Margin {{ $p['margin'] }}%, HPP {{ $rp($p['cogsPerUnit']) }} per botol</p>
                </div>
                <div class="bg-surface rounded-brand border border-line p-4">
                    <p class="text-xs font-semibold text-muted">Untung bersih</p>
                    <p class="mt-1 text-xl font-bold sm:text-2xl {{ $tone($p['cash']) }}">{{ $rp($p['cash']) }}</p>
                    <p class="text-xs text-muted">Sisa bahan senilai {{ $rp($p['leftover']) }}</p>
                </div>
            </section>

            {{-- Uang masuk dan keluar --}}
            <section class="bg-surface rounded-brand border border-line p-5 break-inside-avoid">
                <h2 class="text-lg font-bold text-charcoal">Uang masuk dan keluar</h2>
                <div class="mt-3 grid gap-6 md:grid-cols-2">
                    <dl class="space-y-1.5 text-sm">
                        <div class="flex justify-between"><dt class="text-muted">Penjualan</dt><dd class="font-semibold">{{ $rp($p['revenue']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">HPP (COGS)</dt><dd class="font-semibold">&minus; {{ $rp($p['cogs']) }}</dd></div>
                        <div class="flex justify-between pl-4 text-xs text-muted"><dt>Bahan resep</dt><dd>{{ $rp($p['cogsIngredients']) }}</dd></div>
                        <div class="flex justify-between pl-4 text-xs text-muted"><dt>Biaya per botol (kemasan, upah)</dt><dd>{{ $rp($p['cogsPerBottle']) }}</dd></div>
                        <div class="flex justify-between pl-4 text-xs text-muted"><dt>Biaya per batch</dt><dd>{{ $rp($p['cogsBatch']) }}</dd></div>
                        <div class="flex justify-between border-t border-line pt-2"><dt class="font-bold">Untung kotor</dt><dd class="font-bold {{ $tone($p['gross']) }}">{{ $rp($p['gross']) }}</dd></div>
                    </dl>
                    <dl class="space-y-1.5 text-sm">
                        <div class="flex justify-between"><dt class="text-muted">Penjualan</dt><dd class="font-semibold">{{ $rp($p['revenue']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Belanja bahan</dt><dd class="font-semibold">&minus; {{ $rp($p['spent']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Biaya lain</dt><dd class="font-semibold">&minus; {{ $rp($p['extraSpent']) }}</dd></div>
                        @foreach ($p['extraCosts'] as $cost)
                            <div class="flex justify-between pl-4 text-xs text-muted">
                                <dt>{{ $cost->name }} ({{ $cost->type === 'per_batch' ? 'per batch' : 'per botol'.($cost->size ? ' '.$cost->size : '') }})</dt>
                                <dd>{{ $rp($cost->amount) }}</dd>
                            </div>
                        @endforeach
                        <div class="flex justify-between border-t border-line pt-2"><dt class="font-bold">Untung bersih</dt><dd class="font-bold {{ $tone($p['cash']) }}">{{ $rp($p['cash']) }}</dd></div>
                    </dl>
                </div>
            </section>

            {{-- Per menu / ukuran / manis --}}
            @foreach ([
                ['title' => 'Penjualan per menu', 'head' => 'Menu', 'rows' => $byProduct, 'money' => true],
                ['title' => 'Per ukuran', 'head' => 'Ukuran', 'rows' => $bySize, 'money' => true],
                ['title' => 'Per tingkat manis', 'head' => 'Manis', 'rows' => $bySweetness, 'money' => false],
            ] as $table)
                <section class="bg-surface rounded-brand border border-line overflow-hidden break-inside-avoid">
                    <h2 class="px-5 pt-4 text-lg font-bold text-charcoal">{{ $table['title'] }}</h2>
                    <div class="overflow-x-auto">
                        <table class="mt-2 w-full text-sm">
                            <thead>
                                <tr class="border-b border-line text-left">
                                    <th class="{{ $th }} pl-5">{{ $table['head'] }}</th>
                                    <th class="{{ $th }} text-right">Botol</th>
                                    @if ($table['money'])
                                        <th class="{{ $th }} text-right">Penjualan</th>
                                        <th class="{{ $th }} text-right">HPP</th>
                                        <th class="{{ $th }} pr-5 text-right">Untung</th>
                                    @else
                                        <th class="{{ $th }} pr-5">Porsi</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($table['rows'] as $row)
                                    <tr class="border-b border-line last:border-0">
                                        <td class="{{ $td }} pl-5 font-semibold text-charcoal">
                                            {{ $row['label'] }}
                                            <div class="mt-1 h-1 w-full max-w-[10rem] rounded-full bg-oat" aria-hidden="true">
                                                <div class="h-1 rounded-full bg-brand" style="width: {{ $share($row['bottles']) }}%"></div>
                                            </div>
                                        </td>
                                        <td class="{{ $td }} text-right whitespace-nowrap">{{ $row['bottles'] }} <span class="text-xs text-muted">({{ $share($row['bottles']) }}%)</span></td>
                                        @if ($table['money'])
                                            <td class="{{ $td }} text-right whitespace-nowrap">{{ $rp($row['revenue']) }}</td>
                                            <td class="{{ $td }} text-right whitespace-nowrap text-muted">{{ $rp($row['cost']) }}</td>
                                            <td class="{{ $td }} pr-5 text-right whitespace-nowrap font-semibold {{ $tone($row['profit']) }}">{{ $rp($row['profit']) }}</td>
                                        @else
                                            <td class="{{ $td }} pr-5 text-muted">{{ $share($row['bottles']) }}% dari semua botol</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach

            {{-- Pembeli --}}
            <section class="bg-surface rounded-brand border border-line overflow-hidden break-inside-avoid">
                <h2 class="px-5 pt-4 text-lg font-bold text-charcoal">Pembeli</h2>
                <p class="px-5 text-xs text-muted">{{ count($buyers) }} orang, urut dari belanja terbesar.</p>
                <div class="overflow-x-auto">
                    <table class="mt-2 w-full text-sm">
                        <thead>
                            <tr class="border-b border-line text-left">
                                <th class="{{ $th }} pl-5">Nama</th>
                                <th class="{{ $th }} text-right">Pesanan</th>
                                <th class="{{ $th }} text-right">Botol</th>
                                <th class="{{ $th }} pr-5 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($buyers as $buyer)
                                <tr class="border-b border-line last:border-0">
                                    <td class="{{ $td }} pl-5 font-semibold text-charcoal">{{ $buyer['name'] }}</td>
                                    <td class="{{ $td }} text-right">{{ $buyer['orders'] }}</td>
                                    <td class="{{ $td }} text-right">{{ $buyer['bottles'] }}</td>
                                    <td class="{{ $td }} pr-5 text-right whitespace-nowrap font-semibold">{{ $rp($buyer['total']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- Bahan --}}
        @if ($stock->isNotEmpty())
            <section class="bg-surface rounded-brand border border-line overflow-hidden break-inside-avoid">
                <h2 class="px-5 pt-4 text-lg font-bold text-charcoal">Pemakaian bahan</h2>
                <div class="overflow-x-auto">
                    <table class="mt-2 w-full text-sm">
                        <thead>
                            <tr class="border-b border-line text-left">
                                <th class="{{ $th }} pl-5">Bahan</th>
                                <th class="{{ $th }} text-right">Stok</th>
                                <th class="{{ $th }} text-right">Terpakai</th>
                                <th class="{{ $th }} text-right">Sisa</th>
                                <th class="{{ $th }} pr-5 text-right">Harga beli</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stock->sortBy(fn ($row) => $row->ingredient?->name) as $row)
                                @php $unit = $row->ingredient?->unit; @endphp
                                <tr class="border-b border-line last:border-0">
                                    <td class="{{ $td }} pl-5 font-semibold text-charcoal">{{ $row->ingredient?->name ?? 'Bahan dihapus' }}</td>
                                    <td class="{{ $td }} text-right whitespace-nowrap">{{ $num($row->stock) }} {{ $unit }}</td>
                                    <td class="{{ $td }} text-right whitespace-nowrap">{{ $num($row->used) }} {{ $unit }}</td>
                                    <td class="{{ $td }} text-right whitespace-nowrap {{ $row->remaining() < 0 ? 'font-bold text-amber-text' : '' }}">{{ $num($row->remaining()) }} {{ $unit }}</td>
                                    <td class="{{ $td }} pr-5 text-right whitespace-nowrap {{ $row->cost === null ? 'text-amber-text' : 'text-muted' }}">{{ $row->cost === null ? 'belum diisi' : $rp($row->cost) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</div>
@endsection
