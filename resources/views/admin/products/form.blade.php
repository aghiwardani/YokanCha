@extends('layouts.admin')

@section('title', ($product->exists ? 'Ubah Produk' : 'Tambah Produk').' - YokanCha')

@section('admin-content')
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
                        placeholder="Contoh: 250 ml=32000, 500 ml=60000, 1 liter=110000"
                        value="{{ old('size_options', collect($product->size_options ?? [])->map(fn ($size) => isset(($product->size_prices ?? [])[$size]) ? $size.'='.$product->size_prices[$size] : $size)->implode(', ')) }}"
                        class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('size_options') border-red-500 @enderror"
                    />
                    <p class="text-xs text-charcoal opacity-75 mt-1">Pisahkan dengan koma. Tulis harga per ukuran dengan "=", mis. <code>1 liter=110000</code>. Ukuran tanpa harga memakai Harga Dasar.</p>
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

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="tag" class="block text-sm font-semibold text-charcoal mb-2">Label Kartu</label>
                        <input
                            type="text"
                            id="tag"
                            name="tag"
                            placeholder="Contoh: Maple Signature"
                            value="{{ old('tag', $product->tag) }}"
                            class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('tag') border-red-500 @enderror"
                        />
                        <p class="text-xs text-charcoal opacity-75 mt-1">Opsional. Tampil di pojok foto produk.</p>
                    </div>

                    <div>
                        <label for="sort_order" class="block text-sm font-semibold text-charcoal mb-2">Urutan Tampil</label>
                        <input
                            type="number"
                            id="sort_order"
                            name="sort_order"
                            min="0"
                            value="{{ old('sort_order', $product->sort_order ?? 0) }}"
                            class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('sort_order') border-red-500 @enderror"
                        />
                        <p class="text-xs text-charcoal opacity-75 mt-1">Urutan terkecil tampil pertama sebagai kartu lebar.</p>
                    </div>

                    <div>
                        <label for="tone" class="block text-sm font-semibold text-charcoal mb-2">Warna Latar Kartu</label>
                        <select
                            id="tone"
                            name="tone"
                            class="w-full px-4 py-3 border-2 border-brand rounded-brand bg-white focus:outline-none focus:ring-2 focus:ring-cta"
                        >
                            @foreach (['sage' => 'Sage (hijau)', 'amber' => 'Amber (peach)', 'oat' => 'Oat (krem)'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('tone', $product->tone ?? 'sage') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="bottle_style" class="block text-sm font-semibold text-charcoal mb-2">Ilustrasi Botol</label>
                        <select
                            id="bottle_style"
                            name="bottle_style"
                            class="w-full px-4 py-3 border-2 border-brand rounded-brand bg-white focus:outline-none focus:ring-2 focus:ring-cta"
                        >
                            @foreach (['klasik' => 'Matcha latte', 'maple' => 'Maple (lapisan karamel)', 'oat' => 'Oat (hijau pucat)', 'murni' => 'Matcha murni', 'strawberry' => 'Strawberry (lapisan merah)'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('bottle_style', $product->bottle_style ?? 'klasik') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @php
                    $recipeSizes = ! empty($product->size_options) ? $product->size_options : ['-'];
                    $sugarIngredient = $ingredients->firstWhere('is_sugar', true);
                    $oldRecipe = old('recipe');
                    $oldLessSugar = old('recipe_less_sugar');
                    $fmt = fn ($n) => rtrim(rtrim((string) $n, '0'), '.');
                    $recipeValue = function ($size, $ingredientId) use ($oldRecipe, $recipes, $fmt) {
                        if (is_array($oldRecipe)) {
                            return $oldRecipe[$size][$ingredientId] ?? '';
                        }

                        return isset($recipes[$size][$ingredientId]) ? $fmt($recipes[$size][$ingredientId]->amount) : '';
                    };
                    $lessSugarValue = function ($size) use ($oldLessSugar, $oldRecipe, $recipes, $sugarIngredient, $fmt) {
                        if (is_array($oldRecipe) || is_array($oldLessSugar)) {
                            return $oldLessSugar[$size] ?? '';
                        }
                        $less = $sugarIngredient ? ($recipes[$size][$sugarIngredient->id]->amount_less_sugar ?? null) : null;

                        return $less !== null ? $fmt($less) : '';
                    };
                @endphp

                <div>
                    <h2 class="block text-sm font-semibold text-charcoal mb-2">Resep per Ukuran</h2>
                    @if ($ingredients->isEmpty())
                        <p class="text-xs text-charcoal opacity-75">Belum ada bahan. Tambahkan bahan dulu di halaman bahan.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-charcoal">
                                        <th class="py-2 pr-3 font-semibold">Bahan</th>
                                        @foreach ($recipeSizes as $size)
                                            <th class="py-2 px-1 font-semibold">{{ $size === '-' ? 'Satu ukuran' : $size }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($ingredients as $ingredient)
                                        <tr>
                                            <td class="py-1 pr-3 text-charcoal">{{ $ingredient->name }} ({{ $ingredient->unit }})</td>
                                            @foreach ($recipeSizes as $size)
                                                <td class="py-1 px-1">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        name="recipe[{{ $size }}][{{ $ingredient->id }}]"
                                                        value="{{ $recipeValue($size, $ingredient->id) }}"
                                                        aria-label="{{ $ingredient->name }} untuk {{ $size === '-' ? 'satu ukuran' : $size }}"
                                                        class="w-24 px-2 py-1 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                                                    />
                                                </td>
                                            @endforeach
                                        </tr>
                                        @if ($sugarIngredient && $sugarIngredient->id === $ingredient->id)
                                            <tr>
                                                <td class="py-1 pr-3 text-charcoal opacity-75">Less sugar ({{ $ingredient->unit }})</td>
                                                @foreach ($recipeSizes as $size)
                                                    <td class="py-1 px-1">
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            name="recipe_less_sugar[{{ $size }}]"
                                                            value="{{ $lessSugarValue($size) }}"
                                                            aria-label="Less sugar untuk {{ $size === '-' ? 'satu ukuran' : $size }}"
                                                            class="w-24 px-2 py-1 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                                                        />
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs text-charcoal opacity-75 mt-1">Kosongkan bahan yang tidak dipakai. Simpan dulu, lalu isi resep untuk ukuran baru.</p>
                    @endif
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
