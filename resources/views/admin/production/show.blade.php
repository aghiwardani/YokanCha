@extends('layouts.admin')

@section('title', 'Produksi '.$period->name.' - YokanCha')

@php
    // 1.250,5 style: thousands with dots, decimals only when needed.
    $thousands = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $variantText = fn ($v) => ($v === '' || $v === '-') ? '' : $v;
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
            <button type="button" onclick="window.print()" class="px-4 py-2 rounded-full bg-charcoal text-sm font-semibold text-surface hover:opacity-90 print:hidden">
                Cetak
            </button>
        </div>

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
                                        <th class="px-5 py-2 font-semibold">Total bahan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($variants as $variant)
                                        <tr class="border-b border-line last:border-0 align-top">
                                            <td class="px-5 py-3 font-semibold text-charcoal">{{ $variantText($variant['size']) ?: '-' }}</td>
                                            <td class="px-3 py-3 text-charcoal">{{ $variantText($variant['sweetness']) ?: '-' }}</td>
                                            <td class="px-3 py-3 text-right text-lg font-bold text-charcoal">{{ $variant['bottles'] }}</td>
                                            <td class="px-3 py-3 text-charcoal">
                                                @forelse ($variant['ingredients'] as $id => $amount)
                                                    <div>{{ $ingredients[$id]->name ?? 'Bahan dihapus' }}: <span class="font-semibold">{{ $thousands($amount / $variant['bottles']) }} {{ $ingredients[$id]->unit ?? '' }}</span></div>
                                                @empty
                                                    <span class="text-muted">Resep belum diisi</span>
                                                @endforelse
                                            </td>
                                            <td class="px-5 py-3 text-charcoal">
                                                @foreach ($variant['ingredients'] as $id => $amount)
                                                    <div>{{ $ingredients[$id]->name ?? 'Bahan dihapus' }}: <span class="font-semibold">{{ $thousands($amount) }} {{ $ingredients[$id]->unit ?? '' }}</span></div>
                                                @endforeach
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

            {{-- 3. Pickup list --}}
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
                                        @php $v = collect([$item->size, $item->sweetness])->filter(fn ($x) => $x && $x !== '-')->implode(', '); @endphp
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
