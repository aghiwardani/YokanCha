@extends('layouts.admin')

@section('title', ($product->exists ? 'Ubah Produk' : 'Tambah Produk').' - YokanCha')

@section('admin-content')
<div class="min-h-screen bg-oat py-12 px-4">
    <div class="max-w-2xl mx-auto space-y-8">
        <div class="flex items-center justify-between">
            <h1 class="text-3xl font-bold text-charcoal">
                {{ $product->exists ? 'Ubah Produk' : 'Tambah Produk' }}
            </h1>
            <a href="{{ route('admin.master') }}#produk" class="text-sm font-semibold text-charcoal underline">
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
                        type="text" inputmode="numeric" data-number="int"
                        id="base_price"
                        name="base_price"
                        min="0"
                        value="{{ old('base_price', $product->base_price) }}"
                        class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('base_price') border-red-500 @enderror"
                    />
                </div>

                <fieldset>
                    <legend class="block text-sm font-semibold text-charcoal mb-2">Ukuran</legend>
                    @if ($sizes->isEmpty())
                        <p class="text-xs text-charcoal opacity-75">Belum ada ukuran. Tambahkan dulu di halaman <a href="{{ route('admin.master') }}#ukuran" class="underline">Ukuran</a>.</p>
                    @else
                        @php
                            $oldSizes = old('sizes');
                            $sizeTicked = fn ($size) => is_array($oldSizes)
                                ? ! empty($oldSizes[$size->id]['on'])
                                : in_array($size->name, $product->size_options ?? [], true);
                            $sizePrice = fn ($size) => is_array($oldSizes)
                                ? ($oldSizes[$size->id]['price'] ?? '')
                                : (($product->size_prices ?? [])[$size->name] ?? '');
                        @endphp
                        <div class="space-y-2">
                            @foreach ($sizes as $size)
                                <div class="flex flex-wrap items-center gap-3">
                                    <label class="flex items-center gap-2 min-w-[8rem] text-sm text-charcoal">
                                        <input
                                            type="checkbox"
                                            name="sizes[{{ $size->id }}][on]"
                                            value="1"
                                            data-size-toggle="{{ $size->name }}"
                                            @checked($sizeTicked($size))
                                        />
                                        {{ $size->name }}
                                    </label>
                                    <input
                                        type="text" inputmode="numeric" data-number="int"
                                        name="sizes[{{ $size->id }}][price]"
                                        min="0"
                                        placeholder="Harga (kosong = Harga Dasar)"
                                        value="{{ $sizePrice($size) }}"
                                        aria-label="Harga {{ $size->name }}"
                                        class="w-56 px-3 py-2 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                                    />
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-charcoal opacity-75 mt-1">Centang ukuran yang dijual. Ukuran tanpa harga memakai Harga Dasar. Di menu, ukuran terkecil yang masih tersedia otomatis terpilih.</p>
                    @endif
                    @foreach ($extraSizes as $extra)
                        <p class="mt-2 p-2 text-xs text-charcoal bg-amber-50 border-2 border-amber-400 rounded-brand">
                            {{ $extra }} belum ada di daftar Ukuran. Ukuran ini tetap dijual dengan harga lamanya. Tambahkan di halaman <a href="{{ route('admin.master') }}#ukuran" class="underline">Ukuran</a> dengan nama yang persis sama supaya bisa diatur di sini.
                        </p>
                    @endforeach
                    @error('sizes')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </fieldset>

                <fieldset>
                    <legend class="block text-sm font-semibold text-charcoal mb-2">Tingkat Manis</legend>
                    @if ($sweetnessLevels->isEmpty())
                        <p class="text-xs text-charcoal opacity-75">Belum ada tingkat manis. Tambahkan dulu di halaman <a href="{{ route('admin.master') }}#manis" class="underline">Tingkat Manis</a>.</p>
                    @else
                        @php
                            $oldSweetness = old('sweetness');
                            // After a failed save, show what was ticked (name is always posted).
                            $hasOldForm = old('name') !== null;
                            $sweetnessTicked = fn ($level) => $hasOldForm
                                ? ! empty(($oldSweetness ?? [])[$level->id] ?? null)
                                : in_array($level->name, $product->sweetness_options ?? [], true);
                        @endphp
                        <div class="flex flex-wrap gap-2">
                            @foreach ($sweetnessLevels as $level)
                                <label class="flex items-center gap-2 rounded-chip border border-line bg-surface px-3 py-2 text-sm text-charcoal">
                                    <input type="checkbox" name="sweetness[{{ $level->id }}]" value="1" @checked($sweetnessTicked($level)) />
                                    {{ $level->name }}
                                    <span class="text-xs text-muted">{{ $level->percent }}%</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-charcoal opacity-75 mt-1">Centang tingkat manis yang bisa dipilih pelanggan. Persen menunjukkan berapa banyak gula yang dipakai dibanding takaran Normal di resep. Tidak dicentang sama sekali = tanpa pilihan manis.</p>
                    @endif
                    @foreach ($extraSweetness as $extra)
                        <p class="mt-2 p-2 text-xs text-charcoal bg-amber-50 border-2 border-amber-400 rounded-brand">
                            {{ $extra }} belum ada di daftar Tingkat Manis. Pilihan ini tetap ditawarkan (gulanya {{ App\Models\SweetnessLevel::percentFromName($extra) }}% dari Normal). Tambahkan di halaman <a href="{{ route('admin.master') }}#manis" class="underline">Tingkat Manis</a> dengan nama yang persis sama supaya bisa diatur di sini.
                        </p>
                    @endforeach
                    @error('sweetness')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </fieldset>

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
                    // One column per master size (shown only while ticked, with JS),
                    // plus "Satu ukuran" for a product sold without sizes.
                    $recipeSizes = array_merge($sizes->pluck('name')->all(), $extraSizes, ['-']);
                    // Columns whose amounts come from scaling, not from a saved recipe.
                    $isAutoColumn = fn ($size) => ! is_array(old('recipe')) && isset($recipeDefaults[$size]);
                    $oldRecipe = old('recipe');
                    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
                    // Saved amount, else the amount scaled from the smallest saved size.
                    $recipeValue = function ($size, $ingredientId) use ($oldRecipe, $recipes, $recipeDefaults, $fmt) {
                        if (is_array($oldRecipe)) {
                            return $oldRecipe[$size][$ingredientId] ?? '';
                        }
                        if (isset($recipes[$size])) {
                            return isset($recipes[$size][$ingredientId]) ? $fmt($recipes[$size][$ingredientId]->amount) : '';
                        }

                        return isset($recipeDefaults[$size]['amounts'][$ingredientId]) ? $fmt($recipeDefaults[$size]['amounts'][$ingredientId]) : '';
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
                                            <th class="py-2 px-1 font-semibold {{ $isAutoColumn($size) ? 'bg-amber-50' : '' }}" data-recipe-col="{{ $size }}">{{ $size === '-' ? 'Satu ukuran' : $size }}@if ($isAutoColumn($size)) <span class="block text-xs font-normal opacity-75">(otomatis)</span>@endif</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($ingredients as $ingredient)
                                        <tr>
                                            <td class="py-1 pr-3 text-charcoal">
                                                {{ $ingredient->name }} ({{ $ingredient->unit }})
                                                @if ($ingredient->is_sugar)
                                                    <span class="block text-xs text-muted">Gula: takaran Normal, ikut persen tingkat manis</span>
                                                @endif
                                            </td>
                                            @foreach ($recipeSizes as $size)
                                                <td class="py-1 px-1 {{ $isAutoColumn($size) ? 'bg-amber-50' : '' }}" data-recipe-col="{{ $size }}">
                                                    <input
                                                        type="text" inputmode="decimal" data-number
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
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs text-charcoal opacity-75 mt-1">Isi resep untuk tingkat manis Normal. Takaran bahan gula untuk tingkat lain dihitung otomatis dari persennya (mis. Less sugar 50% = setengahnya).</p>
                        <p class="text-xs text-charcoal opacity-75 mt-1">Kosongkan bahan yang tidak dipakai. Kolom bertanda <strong>(otomatis)</strong> belum punya resep tersimpan: angkanya dihitung dari ukuran terkecil yang sudah punya resep (sesuai isi ml) dan ikut tersimpan saat kamu menekan simpan, jadi sesuaikan dulu kalau perlu.</p>
                        <script>
                            // Show only the recipe columns of ticked sizes; "Satu ukuran"
                            // only when no size is sold. Without JS every column shows.
                            (function () {
                                var toggles = Array.prototype.slice.call(document.querySelectorAll('[data-size-toggle]'));
                                var cells = Array.prototype.slice.call(document.querySelectorAll('[data-recipe-col]'));
                                var hasExtraSizes = {{ count($extraSizes) > 0 ? 'true' : 'false' }};
                                function sync() {
                                    var ticked = {};
                                    var anyTicked = hasExtraSizes;
                                    toggles.forEach(function (box) {
                                        ticked[box.dataset.sizeToggle] = box.checked;
                                        anyTicked = anyTicked || box.checked;
                                    });
                                    cells.forEach(function (cell) {
                                        var col = cell.dataset.recipeCol;
                                        if (col === '-') {
                                            cell.hidden = anyTicked;
                                        } else if (Object.prototype.hasOwnProperty.call(ticked, col)) {
                                            cell.hidden = ! ticked[col];
                                        }
                                    });
                                }
                                toggles.forEach(function (box) { box.addEventListener('change', sync); });
                                sync();
                            })();
                        </script>
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
