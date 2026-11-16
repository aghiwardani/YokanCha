{{-- Bahan: add form + one edit/delete row per ingredient. "_form" tells which form a failed submit came from, so old input lands only there. --}}
@php
    $ingredientAddOld = fn (string $key) => old('_form') === 'ingredient-new' ? old($key) : null;
@endphp
<section id="bahan" class="space-y-8 scroll-mt-8">
    <h2 class="text-2xl font-bold text-charcoal">Bahan</h2>

    <div class="bg-surface rounded-brand shadow-sm p-8">
        <h3 class="text-xl font-bold text-charcoal mb-6">Tambah Bahan</h3>

        <form action="{{ route('admin.ingredients.store') }}" method="POST" class="space-y-6">
            @csrf
            <input type="hidden" name="_form" value="ingredient-new" />

            @if (old('_form') === 'ingredient-new' && $errors->any())
                <div class="w-full p-3 bg-red-50 border-2 border-red-500 rounded-brand">
                    <ul class="text-red-600 text-sm space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label for="new_ingredient_name" class="block text-sm font-semibold text-charcoal mb-2">Nama Bahan</label>
                    <input
                        type="text"
                        id="new_ingredient_name"
                        name="name"
                        value="{{ $ingredientAddOld('name') }}"
                        class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                    />
                </div>
                <div>
                    <label for="new_ingredient_unit" class="block text-sm font-semibold text-charcoal mb-2">Satuan</label>
                    <select
                        id="new_ingredient_unit"
                        name="unit"
                        class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                    >
                        <option value="g" @selected($ingredientAddOld('unit') === 'g')>g</option>
                        <option value="ml" @selected($ingredientAddOld('unit') === 'ml')>ml</option>
                    </select>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-charcoal">
                <input type="checkbox" name="is_sugar" value="1" @checked($ingredientAddOld('is_sugar')) />
                Ini adalah gula
            </label>

            <button
                type="submit"
                class="w-full bg-cta hover:bg-opacity-90 text-surface font-semibold py-3 px-6 rounded-brand transition duration-200"
            >
                Tambah Bahan
            </button>
        </form>
    </div>

    <div class="bg-surface rounded-brand shadow-sm p-8">
        <h3 class="text-xl font-bold text-charcoal mb-6">Daftar Bahan</h3>

        @if ($ingredients->isEmpty())
            <p class="text-charcoal opacity-75">Belum ada bahan.</p>
        @else
            <div class="space-y-4">
                @foreach ($ingredients as $ingredient)
                    @php $failed = old('_form') === 'ingredient-row-'.$ingredient->id; @endphp
                    <div class="flex flex-wrap items-center gap-3 border-2 border-brand rounded-brand p-4">
                        <form
                            action="{{ route('admin.ingredients.update', $ingredient) }}"
                            method="POST"
                            class="flex flex-1 flex-wrap items-center gap-3"
                        >
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_form" value="ingredient-row-{{ $ingredient->id }}" />

                            @if ($failed && $errors->any())
                                <div class="w-full p-3 bg-red-50 border-2 border-red-500 rounded-brand">
                                    <ul class="text-red-600 text-sm space-y-1">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <input
                                type="text"
                                name="name"
                                value="{{ $failed ? old('name') : $ingredient->name }}"
                                aria-label="Nama bahan"
                                class="flex-1 min-w-[10rem] px-3 py-2 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                            />
                            <select
                                name="unit"
                                aria-label="Satuan"
                                class="px-3 py-2 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                            >
                                <option value="g" @selected(($failed ? old('unit') : $ingredient->unit) === 'g')>g</option>
                                <option value="ml" @selected(($failed ? old('unit') : $ingredient->unit) === 'ml')>ml</option>
                            </select>
                            <label class="flex items-center gap-2 text-sm text-charcoal">
                                <input type="checkbox" name="is_sugar" value="1" @checked($failed ? old('is_sugar') : $ingredient->is_sugar) />
                                Gula
                            </label>
                            <button
                                type="submit"
                                class="px-4 py-2 bg-brand text-surface font-semibold rounded-brand hover:bg-opacity-90 transition duration-200"
                            >
                                Simpan
                            </button>
                        </form>
                        <form
                            action="{{ route('admin.ingredients.destroy', $ingredient) }}"
                            method="POST"
                            onsubmit="return confirm({{ Illuminate\Support\Js::from('Hapus bahan '.$ingredient->name.'?') }});"
                        >
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="px-4 py-2 border-2 border-red-500 text-red-600 font-semibold rounded-brand hover:bg-red-50 transition duration-200"
                            >
                                Hapus
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
