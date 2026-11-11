@extends('layouts.app')

@section('title', 'Checkout - YokanCha')

@php($payment = config('yokancha.payment'))

@section('content')
    <main class="yc-container flex max-w-[800px] flex-col gap-6 py-10 lg:py-16">
        <div class="flex flex-col gap-3">
            <h1 class="yc-h2">Checkout</h1>
            <p class="text-base leading-[26px] text-muted">Cukup isi nama dan nomor WhatsApp.</p>
        </div>

        @include('partials.errors')

        @if (empty($lines))
            <div class="flex flex-col items-center gap-4 rounded-card border border-charcoal bg-surface px-8 py-12 text-center">
                <p class="font-display text-[25px] leading-7 text-charcoal">Keranjangmu masih kosong.</p>
                <a href="{{ route('menu.index') }}#menu" class="yc-btn-cta">Lihat menu</a>
            </div>
        @else
            <section class="flex flex-col rounded-card border border-charcoal bg-surface p-5 lg:p-6">
                <h2 class="mb-2 font-display text-[25px] leading-7 text-charcoal">Ringkasan pesanan</h2>

                @foreach ($lines as $line)
                    <div class="flex items-start justify-between gap-4 border-t border-line py-3">
                        <div>
                            <p class="font-bold text-charcoal">{{ $line['product']->name }}</p>
                            <p class="text-sm leading-[22px] text-muted">{{ $line['size'] }} · {{ $line['sweetness'] }} · {{ $line['qty'] }} botol</p>
                        </div>
                        <span class="whitespace-nowrap font-bold text-charcoal">Rp{{ number_format($line['line_total'], 0, ',', '.') }}</span>
                    </div>
                @endforeach

                <div class="flex items-center justify-between border-t border-line pt-4">
                    <span class="font-bold text-charcoal">Total</span>
                    <span class="text-xl font-extrabold leading-5 tracking-[-0.2px] text-charcoal">Rp{{ number_format($subtotal, 0, ',', '.') }}</span>
                </div>
            </section>

            <form method="POST" action="{{ route('checkout.store') }}" class="flex flex-col gap-5 rounded-card border border-charcoal bg-surface p-5 lg:p-6">
                @csrf

                <div class="flex flex-col gap-2">
                    <label for="customer_name" class="yc-mono text-muted">Nama</label>
                    <input
                        type="text"
                        id="customer_name"
                        name="customer_name"
                        value="{{ old('customer_name') }}"
                        autocomplete="name"
                        required
                        class="rounded-brand border border-charcoal bg-surface px-4 py-3 text-base text-charcoal focus:outline focus:outline-2 focus:outline-brand"
                    />
                </div>

                <div class="flex flex-col gap-2">
                    <label for="customer_whatsapp" class="yc-mono text-muted">Nomor WhatsApp</label>
                    <input
                        type="tel"
                        id="customer_whatsapp"
                        name="customer_whatsapp"
                        value="{{ old('customer_whatsapp') }}"
                        placeholder="081234567890"
                        autocomplete="tel"
                        inputmode="tel"
                        required
                        class="rounded-brand border border-charcoal bg-surface px-4 py-3 text-base text-charcoal focus:outline focus:outline-2 focus:outline-brand"
                    />
                </div>

                <p class="rounded-brand bg-sage-soft px-4 py-3 text-sm leading-[22px] text-charcoal">
                    Pembayaran lewat transfer ke {{ $payment['bank'] }} {{ $payment['account_number'] }} a.n. {{ $payment['account_name'] }}. Detailnya muncul lagi setelah pesanan dibuat.
                </p>

                <button type="submit" class="yc-btn-cta self-start">Buat pesanan</button>
            </form>
        @endif
    </main>
@endsection
