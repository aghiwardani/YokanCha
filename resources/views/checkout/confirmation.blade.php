@extends('layouts.app')

@section('title', 'Konfirmasi Pesanan - YokanCha')

@section('content')
    <main class="min-h-screen bg-oat">
        <div class="mx-auto max-w-3xl px-6 py-12">
            <div class="border-b border-brand/10 pb-6">
                <h2 class="text-3xl font-bold text-charcoal">Pesanan Diterima</h2>
                <p class="mt-2 text-charcoal/70">
                    Terima kasih, {{ $order->customer_name }}! Simpan nomor pesananmu di bawah ini.
                </p>
            </div>

            <div class="mt-8 rounded-brand bg-white p-6 shadow-sm">
                <p class="text-sm font-semibold text-charcoal/60">Nomor Pesanan</p>
                <p class="mt-1 text-2xl font-bold text-brand">{{ $order->order_number }}</p>

                <h3 class="mt-6 text-lg font-bold text-charcoal">Ringkasan Pesanan</h3>
                <div class="mt-4 flex flex-col gap-3">
                    @foreach ($order->items as $item)
                        <div class="flex items-center justify-between gap-4 border-b border-brand/10 pb-3">
                            <div>
                                <p class="font-semibold text-charcoal">{{ $item->product->name }}</p>
                                <p class="text-sm text-charcoal/60">
                                    {{ $item->size }} &middot; {{ $item->sweetness }} &middot; Jumlah: {{ $item->qty }}
                                </p>
                            </div>
                            <span class="font-semibold text-charcoal">
                                Rp {{ number_format($item->qty * $item->unit_price, 0, ',', '.') }}
                            </span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <span class="text-lg font-semibold text-charcoal">Total</span>
                    <span class="text-xl font-bold text-charcoal">
                        Rp {{ number_format($total, 0, ',', '.') }}
                    </span>
                </div>
            </div>

            <div class="mt-8 rounded-brand bg-surface p-6 shadow-sm">
                <h3 class="text-lg font-bold text-charcoal">Instruksi Pembayaran</h3>
                <p class="mt-2 text-charcoal/70">
                    Transfer ke BCA 1234567890 a.n. YokanCha, lalu konfirmasi via WhatsApp.
                </p>
            </div>

            <div class="mt-8 text-center">
                <a href="{{ route('menu.index') }}" class="font-semibold text-brand underline">Kembali ke menu</a>
            </div>
        </div>
    </main>
@endsection
