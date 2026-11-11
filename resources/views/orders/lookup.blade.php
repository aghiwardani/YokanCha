@extends('layouts.app')

@section('title', 'Cek Pesanan - YokanCha')

@php
    $paymentLabels = ['unpaid' => 'Belum bayar', 'paid' => 'Sudah bayar'];
    $statusLabels = [
        'new' => 'Baru',
        'diproses' => 'Diproses',
        'siap_diambil' => 'Siap diambil',
        'selesai' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];
@endphp

@section('content')
    <main class="yc-container flex max-w-[800px] flex-col gap-6 py-10 lg:py-16">
        <div class="flex flex-col gap-3">
            <h1 class="yc-h2">Cek pesanan</h1>
            <p class="text-base leading-[26px] text-muted">Masukin nomor pesanan dan nomor WhatsApp yang kamu pakai waktu pesan.</p>
        </div>

        @include('partials.errors')

        <form action="{{ route('orders.lookup.search') }}" method="POST" class="flex flex-col gap-5 rounded-card border border-charcoal bg-surface p-5 lg:p-6">
            @csrf

            <div class="flex flex-col gap-2">
                <label for="order_number" class="yc-mono text-muted">Nomor pesanan</label>
                <input
                    type="text"
                    id="order_number"
                    name="order_number"
                    value="{{ old('order_number', request('order_number')) }}"
                    placeholder="YC-1-ABCDEF"
                    required
                    class="rounded-brand border border-charcoal bg-surface px-4 py-3 text-base uppercase text-charcoal focus:outline focus:outline-2 focus:outline-brand"
                />
            </div>

            <div class="flex flex-col gap-2">
                <label for="customer_whatsapp" class="yc-mono text-muted">Nomor WhatsApp</label>
                <input
                    type="tel"
                    id="customer_whatsapp"
                    name="customer_whatsapp"
                    value="{{ old('customer_whatsapp', request('customer_whatsapp')) }}"
                    placeholder="081234567890"
                    inputmode="tel"
                    required
                    class="rounded-brand border border-charcoal bg-surface px-4 py-3 text-base text-charcoal focus:outline focus:outline-2 focus:outline-brand"
                />
            </div>

            <button type="submit" class="yc-btn-cta self-start">Cari pesanan</button>
        </form>

        @if (! empty($searched))
            @if ($order)
                <section class="flex flex-col gap-4 rounded-card border border-charcoal bg-surface p-5 lg:p-6" aria-live="polite">
                    <div>
                        <p class="yc-mono text-muted">Nomor pesanan</p>
                        <p class="mt-1 font-display text-[32px] leading-9 text-brand">{{ $order->order_number }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-brand bg-sage-soft p-4">
                            <p class="yc-mono text-muted">Pembayaran</p>
                            <p class="mt-1 text-lg font-bold text-charcoal">{{ $paymentLabels[$order->payment_status] ?? $order->payment_status }}</p>
                        </div>
                        <div class="rounded-brand bg-sage-soft p-4">
                            <p class="yc-mono text-muted">Status pesanan</p>
                            <p class="mt-1 text-lg font-bold text-charcoal">{{ $statusLabels[$order->order_status] ?? $order->order_status }}</p>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        @foreach ($order->items as $item)
                            <div class="flex items-start justify-between gap-4 border-t border-line py-3">
                                <div>
                                    <p class="font-bold text-charcoal">{{ $item->product->name }}</p>
                                    <p class="text-sm leading-[22px] text-muted">{{ $item->size }} · {{ $item->sweetness }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-charcoal">{{ $item->qty }} botol</p>
                                    <p class="text-sm text-muted">@ Rp{{ number_format($item->unit_price, 0, ',', '.') }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @else
                <section class="flex flex-col items-center gap-3 rounded-card border border-charcoal bg-surface px-8 py-10 text-center" aria-live="polite">
                    <p class="font-display text-[25px] leading-7 text-charcoal">Pesanannya nggak ketemu</p>
                    <p class="text-base leading-[26px] text-muted">Cek lagi nomor pesanan dan nomor WhatsApp-nya.</p>
                </section>
            @endif
        @endif
    </main>
@endsection
