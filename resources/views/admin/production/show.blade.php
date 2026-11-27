@extends('layouts.admin')

@section('title', 'Produksi '.$period->name.' - YokanCha')

@php
    // 1.250,5 style: thousands with dots, decimals only when needed.
    $thousands = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $variantText = fn ($v) => ($v === '' || $v === '-') ? '' : $v;
    $rp = fn ($n) => ($n < 0 ? '-' : '').'Rp'.number_format(abs(round($n)), 0, ',', '.');
@endphp

@section('admin-content')
<div class="min-h-screen bg-oat py-10 px-4 print:bg-white print:py-0">
    <div class="max-w-5xl mx-auto space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <a href="{{ route('admin.orders', ['po_period_id' => $period->id]) }}" class="text-sm font-semibold text-brand underline print:hidden">Kembali ke pesanan</a>
                <h1 class="mt-2 text-3xl font-bold text-charcoal">Produksi {{ $period->name }}</h1>
                <p class="mt-1 text-sm text-muted">
                    {{ $bottles }} botol dari {{ $orders->count() }} pesanan
                    @if ($period->closes_at)
                        · PO tutup {{ $period->closes_at->timezone(config('app.timezone'))->translatedFormat('j M Y, H.i') }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2 print:hidden">
                <form action="{{ route('admin.periods.production.recipes', $period) }}" method="POST"
                      onsubmit="return confirm('Hitung ulang takaran semua pesanan di batch ini dari resep terbaru di master? Stok terpakai ikut disesuaikan.');">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-full border border-brand bg-surface text-sm font-semibold text-brand hover:bg-sage-soft">
                        Pakai resep terbaru
                    </button>
                </form>
                <form action="{{ route('admin.periods.production.prices', $period) }}" method="POST"
                      onsubmit="return confirm('Ganti harga beli bahan di periode ini dengan harga dari master Bahan?');">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-full border border-brand bg-surface text-sm font-semibold text-brand hover:bg-sage-soft">
                        Pakai harga dari master
                    </button>
                </form>
                <button type="button" onclick="window.print()" class="px-4 py-2 rounded-full bg-charcoal text-sm font-semibold text-surface hover:opacity-90">
                    Cetak
                </button>
            </div>
        </div>

        @if (session('status'))
            <div class="p-4 rounded-brand border border-brand/30 bg-sage-soft text-sm text-charcoal print:hidden" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($stillOpen)
            <div class="p-4 rounded-brand border border-cta/30 bg-amber-soft text-sm text-amber-text print:hidden">
                PO ini masih buka, jadi angkanya masih bisa bertambah. Cek lagi setelah PO tutup sebelum mulai produksi.
            </div>
        @endif

        @if ($orders->isEmpty())
            <div class="bg-surface rounded-brand border border-line p-8 text-center text-charcoal">
                Belum ada pesanan di periode ini.
            </div>
        @else
            {{-- 1. What to make --}}
            <section class="space-y-3">
                <h2 class="text-xl font-bold text-charcoal">Yang harus dibuat</h2>

                @foreach ($products as $productName => $variants)
                    <div class="bg-surface rounded-brand border border-line overflow-hidden break-inside-avoid">
                        <div class="flex items-center justify-between px-5 py-3 bg-sage-soft">
                            <h3 class="font-bold text-brand">{{ $productName }}</h3>
                            <span class="text-sm font-bold text-brand">{{ $variants->sum('bottles') }} botol</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs text-muted border-b border-line">
                                        <th class="px-5 py-2 font-semibold">Ukuran</th>
                                        <th class="px-3 py-2 font-semibold">Manis</th>
                                        <th class="px-3 py-2 font-semibold text-right">Botol</th>
                                        <th class="px-3 py-2 font-semibold">Resep per botol</th>
                                        <th class="px-3 py-2 font-semibold">Total bahan</th>
                                        <th class="px-5 py-2 font-semibold text-right print:hidden">HPP (COGS) / untung per botol</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($variants as $variant)
                                        <tr class="border-b border-line last:border-0 align-top">
                                            <td class="px-5 py-3 font-semibold text-charcoal">{{ $variantText($variant['size']) ?: '-' }}</td>
                                            <td class="px-3 py-3 text-charcoal">
                                                {{ $variantText($variant['sweetness']) ?: '-' }}
                                                @if ($variant['extra'])
                                                    <span class="mt-1 block w-fit rounded-full bg-sage-soft px-2 py-0.5 text-xs font-bold text-brand">Extra matcha</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 text-right text-lg font-bold text-charcoal">{{ $variant['bottles'] }}</td>
                                            <td class="px-3 py-3 text-charcoal">
                                                @forelse ($variant['ingredients'] as $id => $amount)
                                                    <div>{{ $ingredients[$id]->name ?? 'Bahan dihapus' }}: <span class="font-semibold">{{ $thousands($amount / $variant['bottles']) }} {{ $ingredients[$id]->unit ?? '' }}</span></div>
                                                @empty
                                                    <span class="text-muted">Resep belum diisi</span>
                                                @endforelse
                                            </td>
                                            <td class="px-3 py-3 text-charcoal">
                                                @foreach ($variant['ingredients'] as $id => $amount)
                                                    <div>{{ $ingredients[$id]->name ?? 'Bahan dihapus' }}: <span class="font-semibold">{{ $thousands($amount) }} {{ $ingredients[$id]->unit ?? '' }}</span></div>
                                                @endforeach
                                            </td>
                                            @php
                                                $hpp = $variant['cost'] / $variant['bottles'];
                                                $price = $variant['revenue'] / $variant['bottles'];
                                            @endphp
                                            <td class="px-5 py-3 text-right whitespace-nowrap print:hidden">
                                                <div class="text-muted">HPP (COGS) {{ $rp($hpp) }}@unless ($variant['costComplete'])<span title="Ada bahan yang harga belinya belum diisi">*</span>@endunless</div>
                                                <div class="text-muted">Jual {{ $rp($price) }}</div>
                                                <div class="font-bold {{ $price - $hpp >= 0 ? 'text-brand' : 'text-amber-text' }}">Untung {{ $rp($price - $hpp) }}</div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </section>

            {{-- 2. Ingredient totals --}}
            <section class="space-y-3 break-inside-avoid">
                <h2 class="text-xl font-bold text-charcoal">Total bahan yang disiapkan</h2>
                <div class="bg-surface rounded-brand border border-line overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-muted border-b border-line">
                                <th class="px-5 py-2 font-semibold">Bahan</th>
                                <th class="px-3 py-2 font-semibold text-right">Dibutuhkan</th>
                                <th class="px-3 py-2 font-semibold text-right">Stok periode</th>
                                <th class="px-5 py-2 font-semibold text-right">Sisa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ingredients as $id => $ingredient)
                                @php
                                    $row = $stock[$id] ?? null;
                                    $left = $row ? (float) $row->stock - $totals[$id] : null;
                                @endphp
                                <tr class="border-b border-line last:border-0">
                                    <td class="px-5 py-3 font-semibold text-charcoal">{{ $ingredient->name }}</td>
                                    <td class="px-3 py-3 text-right text-lg font-bold text-charcoal">{{ $thousands($totals[$id]) }} <span class="text-sm font-normal">{{ $ingredient->unit }}</span></td>
                                    <td class="px-3 py-3 text-right text-charcoal">{{ $row ? $thousands($row->stock).' '.$ingredient->unit : '-' }}</td>
                                    <td class="px-5 py-3 text-right {{ $left !== null && $left < 0 ? 'font-bold text-amber-text' : 'text-charcoal' }}">
                                        {{ $left === null ? '-' : $thousands($left).' '.$ingredient->unit }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- 3. Money: HPP and profit (not printed for the kitchen) --}}
            <section class="space-y-3 print:hidden">
                <h2 class="text-xl font-bold text-charcoal">Hitungan untung</h2>

                @if ($profit['missingPrices'])
                    <div class="p-4 rounded-brand border border-cta/30 bg-amber-soft text-sm text-amber-text">
                        Harga beli belum diisi untuk: {{ implode(', ', $profit['missingPrices']) }}. HPP dan untung di bawah belum menghitung bahan itu.
                        <a href="{{ route('admin.periods.stock', $period) }}" class="font-semibold underline">Isi di Stok bahan</a>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="bg-surface rounded-brand border border-line p-5 space-y-2">
                        <p class="text-sm font-bold text-charcoal">Dari botol yang terjual</p>
                        <div class="flex justify-between text-sm"><span class="text-muted">Penjualan</span><span class="font-semibold">{{ $rp($profit['revenue']) }}</span></div>
                        <div class="flex justify-between text-sm"><span class="text-muted">HPP (COGS)</span><span class="font-semibold">&minus; {{ $rp($profit['cogs']) }}</span></div>
                        <div class="pl-4 space-y-1 text-xs text-muted">
                            <div class="flex justify-between"><span>Bahan resep</span><span>{{ $rp($profit['cogsIngredients']) }}</span></div>
                            <div class="flex justify-between"><span>Biaya per botol (kemasan, upah)</span><span>{{ $rp($profit['cogsPerBottle']) }}</span></div>
                            <div class="flex justify-between"><span>Biaya per batch</span><span>{{ $rp($profit['cogsBatch']) }}</span></div>
                            <div class="flex justify-between font-semibold"><span>Rata-rata per botol</span><span>{{ $rp($profit['cogsPerUnit']) }}</span></div>
                        </div>
                        <div class="flex justify-between border-t border-line pt-2">
                            <span class="font-bold text-charcoal">Untung kotor</span>
                            <span class="text-lg font-bold {{ $profit['gross'] >= 0 ? 'text-brand' : 'text-amber-text' }}">{{ $rp($profit['gross']) }}</span>
                        </div>
                        <p class="text-xs text-muted">Margin {{ $profit['margin'] }}% dari penjualan.</p>
                        @if ($profit['extraCosts']->isEmpty())
                            <p class="text-xs text-muted">Belum ada biaya kemasan, upah, atau biaya per batch. <a href="{{ route('admin.periods.stock', $period) }}" class="font-semibold underline">Tambah di Stok bahan</a></p>
                        @endif
                    </div>

                    <div class="bg-surface rounded-brand border border-line p-5 space-y-2">
                        <p class="text-sm font-bold text-charcoal">Uang masuk dan keluar batch ini</p>
                        <div class="flex justify-between text-sm"><span class="text-muted">Penjualan</span><span class="font-semibold">{{ $rp($profit['revenue']) }}</span></div>
                        <div class="flex justify-between text-sm"><span class="text-muted">Belanja bahan</span><span class="font-semibold">&minus; {{ $rp($profit['spent']) }}</span></div>
                        <div class="flex justify-between text-sm"><span class="text-muted">Biaya lain (kemasan, upah, per batch)</span><span class="font-semibold">&minus; {{ $rp($profit['extraSpent']) }}</span></div>
                        <div class="flex justify-between border-t border-line pt-2">
                            <span class="font-bold text-charcoal">Untung bersih</span>
                            <span class="text-lg font-bold {{ $profit['cash'] >= 0 ? 'text-brand' : 'text-amber-text' }}">{{ $rp($profit['cash']) }}</span>
                        </div>
                        <p class="text-xs text-muted">
                            Sisa bahan senilai {{ $rp($profit['leftover']) }} masih bisa dipakai di batch berikutnya.
                            Uang yang sudah dibayar pembeli: {{ $rp($profit['paid']) }}.
                        </p>
                    </div>
                </div>
            </section>

            {{-- 4. Pickup list --}}
            <section class="space-y-3">
                <h2 class="text-xl font-bold text-charcoal">Daftar ambil</h2>
                <div class="bg-surface rounded-brand border border-line divide-y divide-line">
                    @foreach ($orders as $order)
                        @php $paid = $order->payment_status === 'paid'; @endphp
                        <div class="flex flex-wrap items-start gap-4 px-5 py-3 break-inside-avoid">
                            <span class="mt-1 h-4 w-4 shrink-0 rounded border-2 border-charcoal" aria-hidden="true"></span>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-charcoal">
                                    {{ $order->customer_name }}
                                    <span class="ml-2 font-mono text-xs font-normal text-muted">{{ $order->order_number }}</span>
                                </p>
                                <ul class="mt-1 text-sm text-charcoal">
                                    @foreach ($order->items as $item)
                                        @php $v = $item->variantLabel(); @endphp
                                        <li><span class="font-semibold">{{ $item->qty }}×</span> {{ optional($item->product)->name }} @if ($v)<span class="text-muted">({{ $v }})</span>@endif</li>
                                    @endforeach
                                </ul>
                            </div>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $paid ? 'bg-sage-soft text-brand' : 'bg-amber-soft text-amber-text' }}">
                                {{ $paid ? 'Sudah Bayar' : 'Belum Bayar' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
@endsection
