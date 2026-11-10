@extends('layouts.app')

@section('title', 'Menu - YokanCha')

@section('content')
    <main class="min-h-screen bg-oat">
        <div class="mx-auto max-w-6xl px-6 py-12">
            <div class="flex flex-col gap-2 border-b border-brand/10 pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-3xl font-bold text-charcoal">Menu</h2>
                    <p class="mt-2 max-w-xl text-charcoal/70">
                        Pilih minuman matcha favoritmu untuk periode PO yang sedang berjalan.
                    </p>
                </div>

                @if ($period)
                    <span class="inline-block rounded-brand bg-brand/10 px-4 py-2 text-sm font-semibold text-brand">
                        {{ $period->name }}
                    </span>
                @endif
            </div>

            @if (! $period)
                <div class="mt-16 flex flex-col items-center justify-center rounded-brand border-2 border-dashed border-brand/30 bg-white px-8 py-16 text-center">
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-brand/10">
                        <span class="text-2xl">🍵</span>
                    </div>
                    <p class="text-lg font-semibold text-charcoal">
                        Belum ada periode PO yang dibuka.
                    </p>
                    <p class="mt-2 text-charcoal/60">
                        Pantau terus ya!
                    </p>
                </div>
            @else
                <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($items as $item)
                        @php
                            $product = $item->product;
                            $remaining = $item->remaining();
                            $soldOut = $remaining <= 0;
                        @endphp

                        <div class="relative flex flex-col overflow-hidden rounded-brand bg-surface shadow-sm {{ $soldOut ? 'opacity-60' : '' }}">
                            <div class="absolute right-3 top-3 z-10">
                                @if ($soldOut)
                                    <span class="rounded-brand bg-charcoal/80 px-3 py-1 text-xs font-semibold text-white">
                                        Habis
                                    </span>
                                @else
                                    <span class="rounded-brand bg-cta/10 px-3 py-1 text-xs font-semibold text-cta">
                                        Sisa {{ $remaining }}
                                    </span>
                                @endif
                            </div>

                            <div class="flex h-40 items-center justify-center bg-brand/10">
                                @if ($product->photo_path)
                                    <img src="{{ asset($product->photo_path) }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                                @else
                                    <span class="text-5xl">🍵</span>
                                @endif
                            </div>

                            <div class="flex flex-1 flex-col gap-3 p-5">
                                <div>
                                    <h3 class="text-lg font-bold text-charcoal">{{ $product->name }}</h3>
                                    @if ($product->description)
                                        <p class="mt-1 text-sm text-charcoal/60">{{ $product->description }}</p>
                                    @endif
                                </div>

                                @if (! empty($product->size_options))
                                    <div>
                                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-charcoal/50">Ukuran</p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($product->size_options as $index => $size)
                                                <button
                                                    type="button"
                                                    disabled
                                                    class="rounded-brand border px-3 py-1 text-sm font-medium {{ $index === 0 ? 'border-brand bg-brand text-white' : 'border-brand/30 bg-white text-charcoal' }}"
                                                >
                                                    {{ $size }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if (! empty($product->sweetness_options))
                                    <div>
                                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-charcoal/50">Tingkat Manis</p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($product->sweetness_options as $index => $sweetness)
                                                <button
                                                    type="button"
                                                    disabled
                                                    class="rounded-brand border px-3 py-1 text-sm font-medium {{ $index === 0 ? 'border-brand bg-brand text-white' : 'border-brand/30 bg-white text-charcoal' }}"
                                                >
                                                    {{ $sweetness }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="mt-auto flex items-center justify-between pt-2">
                                    <span class="text-lg font-bold text-charcoal">
                                        Rp {{ number_format($product->base_price, 0, ',', '.') }}
                                    </span>

                                    @if ($soldOut)
                                        <button
                                            type="button"
                                            disabled
                                            class="cursor-not-allowed rounded-brand bg-charcoal/20 px-5 py-2 text-sm font-semibold text-charcoal/50"
                                        >
                                            Habis
                                        </button>
                                    @else
                                        <a
                                            href="#"
                                            class="rounded-brand bg-cta px-5 py-2 text-sm font-semibold text-white transition hover:bg-cta/90"
                                        >
                                            Tambah
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </main>
@endsection
