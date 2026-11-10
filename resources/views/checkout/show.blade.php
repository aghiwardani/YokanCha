@extends('layouts.app')

@section('title', 'Checkout - YokanCha')

@section('content')
    <main class="min-h-screen bg-oat">
        <div class="mx-auto max-w-3xl px-6 py-12">
            <div class="border-b border-brand/10 pb-6">
                <h2 class="text-3xl font-bold text-charcoal">Checkout</h2>
                <p class="mt-2 text-charcoal/70">
                    Lengkapi data diri untuk menyelesaikan pesananmu.
                </p>
            </div>

            @if ($errors->any())
                <div class="mt-6 rounded-brand border border-cta/40 bg-cta/10 p-4 text-sm text-cta">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (empty($lines))
                <div class="mt-16 flex flex-col items-center justify-center rounded-brand border-2 border-dashed border-brand/30 bg-white px-8 py-16 text-center">
                    <p class="text-lg font-semibold text-charcoal">Keranjang masih kosong.</p>
                    <p class="mt-2 text-charcoal/60">
                        <a href="{{ route('menu.index') }}" class="font-semibold text-brand underline">Kembali ke menu</a>
                        untuk mulai memilih minuman.
                    </p>
                </div>
            @else
                <div class="mt-8 rounded-brand bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-charcoal">Ringkasan Pesanan</h3>

                    <div class="mt-4 flex flex-col gap-3">
                        @foreach ($lines as $line)
                            <div class="flex items-center justify-between gap-4 border-b border-brand/10 pb-3">
                                <div>
                                    <p class="font-semibold text-charcoal">{{ $line['product']->name }}</p>
                                    <p class="text-sm text-charcoal/60">
                                        {{ $line['size'] }} &middot; {{ $line['sweetness'] }} &middot; Jumlah: {{ $line['qty'] }}
                                    </p>
                                </div>
                                <span class="font-semibold text-charcoal">
                                    Rp {{ number_format($line['line_total'], 0, ',', '.') }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-lg font-semibold text-charcoal">Subtotal</span>
                        <span class="text-xl font-bold text-charcoal">
                            Rp {{ number_format($subtotal, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                <form method="POST" action="{{ route('checkout.store') }}" class="mt-8 flex flex-col gap-4 rounded-brand bg-surface p-6 shadow-sm">
                    @csrf

                    <div>
                        <label for="customer_name" class="block text-sm font-semibold text-charcoal">Nama</label>
                        <input
                            type="text"
                            id="customer_name"
                            name="customer_name"
                            value="{{ old('customer_name') }}"
                            class="mt-1 w-full rounded-brand border border-charcoal/20 px-4 py-2 text-charcoal focus:border-brand focus:outline-none"
                            required
                        />
                    </div>

                    <div>
                        <label for="customer_whatsapp" class="block text-sm font-semibold text-charcoal">Nomor WhatsApp</label>
                        <input
                            type="text"
                            id="customer_whatsapp"
                            name="customer_whatsapp"
                            value="{{ old('customer_whatsapp') }}"
                            class="mt-1 w-full rounded-brand border border-charcoal/20 px-4 py-2 text-charcoal focus:border-brand focus:outline-none"
                            required
                        />
                    </div>

                    <button
                        type="submit"
                        class="mt-2 w-full rounded-brand bg-cta px-5 py-3 text-center text-base font-semibold text-white transition hover:bg-cta/90"
                    >
                        Konfirmasi Pesanan
                    </button>
                </form>
            @endif
        </div>
    </main>
@endsection
