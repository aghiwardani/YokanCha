@extends('layouts.admin')

@section('title', 'Produk - YokanCha')

@section('admin-content')
<div class="min-h-screen bg-oat py-12 px-4">
    <div class="max-w-5xl mx-auto space-y-8">
        <div class="flex items-center justify-between">
            <h1 class="text-3xl font-bold text-charcoal">Produk</h1>
            <div class="flex items-center gap-4">
                <a href="{{ route('admin.products.create') }}" class="bg-cta hover:bg-opacity-90 text-surface font-semibold py-2 px-4 rounded-brand transition duration-200">
                    Tambah Produk
                </a>
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-charcoal underline">
                        Keluar
                    </button>
                </form>
            </div>
        </div>

        @if (session('status'))
            <div class="p-4 bg-brand bg-opacity-10 border-2 border-brand rounded-brand text-charcoal text-sm">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="p-4 bg-red-50 border-2 border-red-500 rounded-brand">
                <ul class="text-red-600 text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-surface rounded-brand shadow-sm p-8">
            <h2 class="text-xl font-bold text-charcoal mb-6">Daftar Produk</h2>

            @if ($products->isEmpty())
                <p class="text-charcoal opacity-75">Belum ada produk.</p>
            @else
                <div class="space-y-6">
                    @foreach ($products as $product)
                        <div class="border-2 border-brand rounded-brand p-4 space-y-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-charcoal">{{ $product->name }}</p>
                                    <p class="text-sm text-charcoal opacity-75">{{ $product->description }}</p>
                                    <p class="text-sm text-charcoal mt-1">
                                        Harga Dasar: Rp {{ number_format($product->base_price, 0, ',', '.') }}
                                    </p>
                                    <p class="text-xs text-charcoal opacity-75 mt-1">
                                        Ukuran: {{ implode(', ', $product->size_options ?? []) ?: '-' }}
                                        &middot;
                                        Tingkat Manis: {{ implode(', ', $product->sweetness_options ?? []) ?: '-' }}
                                    </p>
                                </div>

                                <a href="{{ route('admin.products.edit', $product) }}" class="px-4 py-2 bg-charcoal text-surface font-semibold rounded-brand hover:bg-opacity-90 transition duration-200 whitespace-nowrap">
                                    Ubah
                                </a>
                            </div>

                            @if ($periods->isNotEmpty())
                                <div class="border-t border-brand pt-4">
                                    <p class="text-sm font-semibold text-charcoal mb-2">Atur Kuota per Periode</p>
                                    <form action="{{ route('admin.products.quota', $product) }}" method="POST" class="flex flex-wrap items-end gap-3">
                                        @csrf
                                        <div>
                                            <label class="block text-xs font-semibold text-charcoal mb-1">Periode</label>
                                            <select name="po_period_id" class="px-3 py-2 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta">
                                                @foreach ($periods as $period)
                                                    <option value="{{ $period->id }}">{{ $period->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-charcoal mb-1">Kuota</label>
                                            <input type="number" name="quota" min="0" class="w-24 px-3 py-2 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta" />
                                        </div>
                                        <button type="submit" class="px-4 py-2 bg-brand text-surface font-semibold rounded-brand hover:bg-opacity-90 transition duration-200">
                                            Simpan Kuota
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
