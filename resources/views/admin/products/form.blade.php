@extends('layouts.app')

@section('title', ($product->exists ? 'Ubah Produk' : 'Tambah Produk').' - YokanCha')

@section('content')
<div class="min-h-screen bg-oat py-12 px-4">
    <div class="max-w-2xl mx-auto space-y-8">
        <div class="flex items-center justify-between">
            <h1 class="text-3xl font-bold text-charcoal">
                {{ $product->exists ? 'Ubah Produk' : 'Tambah Produk' }}
            </h1>
            <a href="{{ route('admin.products') }}" class="text-sm font-semibold text-charcoal underline">
                Kembali
            </a>
        </div>

        <div class="bg-surface rounded-brand shadow-sm p-8">
            @if ($errors->any())
                <div class="mb-6 p-4 bg-red-50 border-2 border-red-500 rounded-brand">
                    <ul class="text-red-600 text-sm space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form
                action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
                method="POST"
                class="space-y-6"
            >
                @csrf
                @if ($product->exists)
                    @method('PUT')
                @endif

                <div>
                    <label for="name" class="block text-sm font-semibold text-charcoal mb-2">Nama Produk</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name', $product->name) }}"
                        class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('name') border-red-500 @enderror"
                    />
                </div>

                <div>
                    <label for="category" class="block text-sm font-semibold text-charcoal mb-2">Kategori</label>
                    <input
                        type="text"
                        id="category"
                        name="category"
                        value="{{ old('category', $product->category ?? 'minuman') }}"
                        class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('category') border-red-500 @enderror"
                    />
                </div>

                <div>
                    <label for="description" class="block text-sm font-semibold text-charcoal mb-2">Deskripsi</label>
                    <textarea
                        id="description"
                        name="description"
                        rows="3"
                        class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('description') border-red-500 @enderror"
                    >{{ old('description', $product->description) }}</textarea>
                </div>

                <div>
                    <label for="base_price" class="block text-sm font-semibold text-charcoal mb-2">Harga Dasar</label>
                    <input
                        type="number"
                        id="base_price"
                        name="base_price"
                        min="0"
                        value="{{ old('base_price', $product->base_price) }}"
                        class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('base_price') border-red-500 @enderror"
                    />
                </div>

                <div>
                    <label for="size_options" class="block text-sm font-semibold text-charcoal mb-2">Ukuran</label>
                    <input
                        type="text"
                        id="size_options"
                        name="size_options"
                        placeholder="Contoh: 250ml, 1L"
                        value="{{ old('size_options', implode(', ', $product->size_options ?? [])) }}"
                        class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('size_options') border-red-500 @enderror"
                    />
                    <p class="text-xs text-charcoal opacity-75 mt-1">Pisahkan dengan koma.</p>
                </div>

                <div>
                    <label for="sweetness_options" class="block text-sm font-semibold text-charcoal mb-2">Tingkat Manis</label>
                    <input
                        type="text"
                        id="sweetness_options"
                        name="sweetness_options"
                        placeholder="Contoh: Normal, Kurang Gula"
                        value="{{ old('sweetness_options', implode(', ', $product->sweetness_options ?? [])) }}"
                        class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('sweetness_options') border-red-500 @enderror"
                    />
                    <p class="text-xs text-charcoal opacity-75 mt-1">Pisahkan dengan koma.</p>
                </div>

                <button
                    type="submit"
                    class="w-full bg-cta hover:bg-opacity-90 text-surface font-semibold py-3 px-6 rounded-brand transition duration-200"
                >
                    {{ $product->exists ? 'Simpan Perubahan' : 'Tambah Produk' }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
