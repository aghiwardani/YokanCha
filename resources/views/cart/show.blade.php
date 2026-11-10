@extends('layouts.app')

@section('title', 'Keranjang - YokanCha')

@section('content')
    <main class="min-h-screen bg-oat">
        <div class="mx-auto max-w-3xl px-6 py-12">
            <div class="border-b border-brand/10 pb-6">
                <h2 class="text-3xl font-bold text-charcoal">Keranjang</h2>
                <p class="mt-2 text-charcoal/70">
                    Periksa pesananmu sebelum lanjut ke checkout.
                </p>
            </div>

            @if (empty($lines))
                <div class="mt-16 flex flex-col items-center justify-center rounded-brand border-2 border-dashed border-brand/30 bg-white px-8 py-16 text-center">
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-brand/10">
                        <span class="text-2xl">🛒</span>
                    </div>
                    <p class="text-lg font-semibold text-charcoal">
                        Keranjang masih kosong.
                    </p>
                    <p class="mt-2 text-charcoal/60">
                        <a href="{{ route('menu.index') }}" class="font-semibold text-brand underline">Kembali ke menu</a>
                        untuk mulai memilih minuman.
                    </p>
                </div>
            @else
                <div class="mt-8 flex flex-col gap-4">
                    @foreach ($lines as $line)
                        <div class="flex items-center justify-between gap-4 rounded-brand bg-surface p-5 shadow-sm">
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-charcoal">{{ $line['product']->name }}</h3>
                                <p class="mt-1 text-sm text-charcoal/60">
                                    {{ $line['size'] }} &middot; {{ $line['sweetness'] }}
                                </p>
                                <p class="mt-1 text-sm text-charcoal/70">
                                    Jumlah: {{ $line['qty'] }}
                                </p>
                            </div>

                            <div class="flex flex-col items-end gap-3">
                                <span class="text-lg font-bold text-charcoal">
                                    Rp {{ number_format($line['line_total'], 0, ',', '.') }}
                                </span>

                                <form method="POST" action="{{ route('cart.remove', $line['line_key']) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="rounded-brand border border-charcoal/20 px-3 py-1 text-sm font-semibold text-charcoal/70 transition hover:border-cta hover:text-cta"
                                    >
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8 flex flex-col gap-4 rounded-brand bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-lg font-semibold text-charcoal">Subtotal</span>
                        <span class="text-xl font-bold text-charcoal">
                            Rp {{ number_format($subtotal, 0, ',', '.') }}
                        </span>
                    </div>

                    <a
                        href="{{ Route::has('checkout.show') ? route('checkout.show') : '#' }}"
                        class="w-full rounded-brand bg-cta px-5 py-3 text-center text-base font-semibold text-white transition hover:bg-cta/90"
                    >
                        Lanjut ke Checkout
                    </a>
                </div>
            @endif
        </div>
    </main>
@endsection
