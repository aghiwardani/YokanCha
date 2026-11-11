@extends('layouts.app')

@section('title', 'Keranjang - YokanCha')

@section('content')
    <main class="yc-container flex max-w-[800px] flex-col gap-6 py-10 lg:py-16">
        <div class="flex flex-col gap-3">
            <h1 class="yc-h2">Keranjang</h1>
            <p class="text-base leading-[26px] text-muted">
                Cek lagi pesananmu sebelum checkout. Maksimal {{ config('yokancha.max_per_variant') }} botol per rasa.
            </p>
        </div>

        @include('partials.errors')

        @if (empty($lines))
            <div class="flex flex-col items-center gap-4 rounded-card border border-charcoal bg-surface px-8 py-12 text-center">
                <img src="{{ asset('images/landing/yoka-lean.png') }}" alt="" class="yc-mascot yc-mascot-breathe h-[140px] w-auto">
                <p class="font-display text-[25px] leading-7 text-charcoal">Keranjangmu masih kosong.</p>
                <a href="{{ route('menu.index') }}#menu" class="yc-btn-cta">Lihat menu</a>
            </div>
        @else
            <div class="flex flex-col gap-3">
                @foreach ($lines as $line)
                    <article class="flex flex-col gap-4 rounded-card border border-charcoal bg-surface p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-col gap-1">
                            <h2 class="font-display text-[22px] leading-7 text-charcoal">{{ $line['product']->name }}</h2>
                            <p class="text-sm leading-[22px] text-muted">
                                {{ $line['size'] }} · {{ $line['sweetness'] }} · Rp{{ number_format($line['unit_price'], 0, ',', '.') }}/botol
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <form method="POST" action="{{ route('cart.update', $line['line_key']) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <label for="qty-{{ $loop->index }}" class="yc-mono text-muted">Jumlah</label>
                                <input
                                    id="qty-{{ $loop->index }}"
                                    type="number"
                                    name="qty"
                                    min="0"
                                    max="{{ config('yokancha.max_per_variant') }}"
                                    value="{{ $line['qty'] }}"
                                    class="w-16 rounded-chip border border-charcoal bg-surface px-3 py-2 text-sm font-bold text-charcoal focus:outline focus:outline-2 focus:outline-brand"
                                />
                                <button type="submit" class="rounded-chip border border-charcoal px-3 py-2 text-sm font-bold text-charcoal hover:bg-charcoal/5">Ubah</button>
                            </form>

                            <form method="POST" action="{{ route('cart.remove', $line['line_key']) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-chip px-3 py-2 text-sm font-bold text-amber-text underline hover:no-underline">Hapus</button>
                            </form>

                            <span class="ml-auto whitespace-nowrap text-lg font-extrabold text-charcoal sm:ml-2">Rp{{ number_format($line['line_total'], 0, ',', '.') }}</span>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="flex flex-col gap-4 rounded-card border border-charcoal bg-surface p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-baseline gap-3">
                    <span class="font-bold text-charcoal">Subtotal</span>
                    <span class="text-xl font-extrabold leading-5 tracking-[-0.2px] text-charcoal">Rp{{ number_format($subtotal, 0, ',', '.') }}</span>
                </div>
                <a href="{{ route('checkout.show') }}" class="yc-btn-cta">Lanjut ke checkout</a>
            </div>
        @endif
    </main>
@endsection
