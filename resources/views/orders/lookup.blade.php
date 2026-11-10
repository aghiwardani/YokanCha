@extends('layouts.app')

@section('title', 'Cari Pesanan - YokanCha')

@section('content')
<div class="min-h-screen bg-oat py-12 px-4">
    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-charcoal mb-2">Cari Pesanan Anda</h1>
            <p class="text-charcoal opacity-75">Masukkan nomor pesanan dan nomor WhatsApp untuk melihat status pesanan Anda</p>
        </div>

        <!-- Search Form -->
        <div class="bg-surface rounded-brand shadow-sm p-8 mb-8">
            <form action="{{ route('orders.lookup.search') }}" method="POST" class="space-y-6">
                @csrf

                <!-- Order Number Input -->
                <div>
                    <label for="order_number" class="block text-sm font-semibold text-charcoal mb-2">
                        Nomor Pesanan
                    </label>
                    <input
                        type="text"
                        id="order_number"
                        name="order_number"
                        value="{{ old('order_number') }}"
                        placeholder="Contoh: YC-1-ABCDEF"
                        class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('order_number') border-red-500 @enderror"
                    />
                    @error('order_number')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- WhatsApp Number Input -->
                <div>
                    <label for="customer_whatsapp" class="block text-sm font-semibold text-charcoal mb-2">
                        Nomor WhatsApp
                    </label>
                    <input
                        type="text"
                        id="customer_whatsapp"
                        name="customer_whatsapp"
                        value="{{ old('customer_whatsapp') }}"
                        placeholder="Contoh: 081234567890"
                        class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('customer_whatsapp') border-red-500 @enderror"
                    />
                    @error('customer_whatsapp')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    class="w-full bg-cta hover:bg-opacity-90 text-surface font-semibold py-3 px-6 rounded-brand transition duration-200"
                >
                    Cari Pesanan
                </button>
            </form>
        </div>

        <!-- Search Results -->
        @if (isset($searched) && $searched)
            @if ($order)
                <div class="bg-surface rounded-brand shadow-sm p-8">
                    <!-- Order Header -->
                    <div class="mb-6 pb-6 border-b-2 border-oat">
                        <h2 class="text-2xl font-bold text-charcoal mb-4">{{ $order->order_number }}</h2>

                        <!-- Status Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Payment Status -->
                            <div class="bg-oat rounded-brand p-4">
                                <p class="text-sm font-semibold text-charcoal opacity-75 mb-1">Status Pembayaran</p>
                                <p class="text-lg font-bold text-brand">
                                    {{ [
                                        'unpaid' => 'Belum Bayar',
                                        'paid' => 'Sudah Bayar',
                                    ][$order->payment_status] ?? $order->payment_status }}
                                </p>
                            </div>

                            <!-- Order Status -->
                            <div class="bg-oat rounded-brand p-4">
                                <p class="text-sm font-semibold text-charcoal opacity-75 mb-1">Status Pesanan</p>
                                <p class="text-lg font-bold text-brand">
                                    {{ [
                                        'new' => 'Baru',
                                        'diproses' => 'Diproses',
                                        'siap_diambil' => 'Siap Diambil',
                                        'selesai' => 'Selesai',
                                        'cancelled' => 'Dibatalkan',
                                    ][$order->order_status] ?? $order->order_status }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div>
                        <h3 class="text-lg font-bold text-charcoal mb-4">Daftar Pesanan</h3>
                        <div class="space-y-3">
                            @foreach ($order->items as $item)
                                <div class="flex justify-between items-center p-4 bg-oat rounded-brand">
                                    <div class="flex-1">
                                        <p class="font-semibold text-charcoal">{{ $item->product->name }}</p>
                                        <p class="text-sm text-charcoal opacity-75">{{ $item->size }}, {{ $item->sweetness }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-charcoal">{{ $item->qty }}</p>
                                        <p class="text-sm text-charcoal opacity-75">item@Rp {{ number_format($item->unit_price, 0, ',', '.') }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <!-- Not Found Message -->
                <div class="bg-surface rounded-brand shadow-sm p-8 text-center">
                    <div class="mb-4">
                        <p class="text-5xl mb-4">🔍</p>
                    </div>
                    <h2 class="text-2xl font-bold text-charcoal mb-2">Pesanan tidak ditemukan</h2>
                    <p class="text-charcoal opacity-75 mb-6">
                        Periksa kembali nomor pesanan dan nomor WhatsApp Anda, lalu coba lagi.
                    </p>
                    <button
                        onclick="window.history.back()"
                        class="inline-block bg-brand hover:bg-opacity-90 text-surface font-semibold py-3 px-8 rounded-brand transition duration-200"
                    >
                        Coba Lagi
                    </button>
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
