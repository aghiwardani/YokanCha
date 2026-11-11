@extends('layouts.app')

@section('title', 'Pesanan Diterima - YokanCha')

@php($payment = config('yokancha.payment'))

@section('content')
    <main class="yc-container flex max-w-[800px] flex-col gap-6 py-10 lg:py-16">
        <div class="flex flex-col gap-3">
            <span class="yc-tag-amber self-start">Pesanan diterima</span>
            <h1 class="yc-h2">Makasih, {{ $order->customer_name }}.</h1>
            <p class="text-base leading-[26px] text-muted">
                Pesananmu sudah tercatat. Kirim pesanannya ke WhatsApp aku supaya bisa dicek.
            </p>
        </div>

        <section class="flex flex-col gap-4 rounded-card border border-charcoal bg-surface p-5 lg:p-6">
            <div>
                <p class="yc-mono text-muted">Nomor pesanan</p>
                <p class="mt-1 font-display text-[32px] leading-9 text-brand">{{ $order->order_number }}</p>
                <p class="mt-1 text-sm leading-[22px] text-muted">Simpan nomor ini buat cek status pesananmu nanti.</p>
            </div>

            <div class="flex flex-col">
                @foreach ($order->items as $item)
                    <div class="flex items-start justify-between gap-4 border-t border-line py-3">
                        <div>
                            <p class="font-bold text-charcoal">{{ $item->product->name }}</p>
                            <p class="text-sm leading-[22px] text-muted">{{ $item->size }} · {{ $item->sweetness }} · {{ $item->qty }} botol</p>
                        </div>
                        <span class="whitespace-nowrap font-bold text-charcoal">Rp{{ number_format($item->qty * $item->unit_price, 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center justify-between border-t border-line pt-4">
                <span class="font-bold text-charcoal">Total</span>
                <span class="text-xl font-extrabold leading-5 tracking-[-0.2px] text-charcoal">Rp{{ number_format($total, 0, ',', '.') }}</span>
            </div>
        </section>

        <section class="flex flex-col gap-4 rounded-card border border-charcoal bg-sage-soft p-5 lg:p-6">
            <h2 class="font-display text-[25px] leading-7 text-charcoal">Cara bayar</h2>
            <ol class="flex flex-col gap-2 text-base leading-[26px] text-charcoal">
                <li>1. Transfer <strong>Rp{{ number_format($total, 0, ',', '.') }}</strong> ke {{ $payment['bank'] }} <strong>{{ $payment['account_number'] }}</strong> a.n. {{ $payment['account_name'] }}.</li>
                <li>2. Kirim pesanan ke WhatsApp aku lewat tombol di bawah, sekalian lampirkan bukti transfer.</li>
                <li>3. Aku kabari kalau pembayaran sudah dicek dan pesanan siap diambil di kantor.</li>
            </ol>

            <div class="flex flex-wrap gap-3">
                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="yc-btn-cta">Kirim lewat WhatsApp</a>
                <a href="{{ route('orders.lookup') }}" class="yc-btn-ghost bg-surface">Cek status pesanan</a>
            </div>
        </section>

        <a href="{{ route('menu.index') }}" class="self-start text-sm font-bold text-brand underline">Balik ke menu</a>
    </main>
@endsection
