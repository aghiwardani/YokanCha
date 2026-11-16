{{-- Bahan: add form, then one row per ingredient that opens into its edit/delete panel. "_form" tells which form a failed submit came from, so old input lands only there. --}}
@php
    $ingredientAddOld = fn (string $key) => old('_form') === 'ingredient-new' ? old($key) : null;
    $field = 'w-full rounded-chip border border-line bg-surface px-3 py-2 text-sm text-charcoal focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30';
@endphp
<section id="bahan" role="tabpanel" aria-labelledby="tab-bahan" class="scroll-mt-8">
    <p class="mb-4 max-w-xl text-sm leading-6 text-muted">
        Bahan baku yang stoknya kamu isi di tiap periode. Resep produk memakai bahan dari daftar ini, dan satu bahan bisa ditandai sebagai gula untuk takaran Less sugar.
    </p>

    <form action="{{ route('admin.ingredients.store') }}" method="POST" class="mb-4 rounded-brand border border-line bg-surface p-4">
        @csrf
        <input type="hidden" name="_form" value="ingredient-new" />
        <p class="mb-3 text-sm font-bold text-charcoal">Tambah bahan</p>

        @if (old('_form') === 'ingredient-new' && $errors->any())
            <ul class="mb-3 space-y-1 rounded-chip bg-red-50 px-3 py-2 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_7rem_auto_auto] sm:items-end">
            <div>
                <label for="new_ingredient_name" class="mb-1 block text-xs font-bold text-muted">Nama bahan</label>
                <input type="text" id="new_ingredient_name" name="name" placeholder="Contoh: Matcha Tokyo" value="{{ $ingredientAddOld('name') }}" class="{{ $field }}" />
            </div>
            <div>
                <label for="new_ingredient_unit" class="mb-1 block text-xs font-bold text-muted">Satuan</label>
                <select id="new_ingredient_unit" name="unit" class="{{ $field }}">
                    <option value="g" @selected($ingredientAddOld('unit') === 'g')>gram (g)</option>
                    <option value="ml" @selected($ingredientAddOld('unit') === 'ml')>mililiter (ml)</option>
                </select>
            </div>
            <label class="flex items-center gap-2 py-2 text-sm text-charcoal">
                <input type="checkbox" name="is_sugar" value="1" class="h-4 w-4 rounded border-line text-brand focus:ring-brand" @checked($ingredientAddOld('is_sugar')) />
                Ini gula
            </label>
            <button type="submit" class="rounded-brand bg-cta px-4 py-2 text-sm font-bold text-surface transition hover:bg-opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-cta focus-visible:ring-offset-2">
                Tambah bahan
            </button>
        </div>
    </form>

    <div class="overflow-hidden rounded-brand border border-line bg-surface">
        @if ($ingredients->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="font-bold text-charcoal">Belum ada bahan.</p>
                <p class="mt-1 text-sm text-muted">Tambahkan bahan di atas, misalnya matcha, susu, dan gula cair.</p>
            </div>
        @else
            <div class="hidden grid-cols-[minmax(0,2fr)_6rem_minmax(0,2fr)_5rem] gap-4 border-b border-line bg-oat px-5 py-2.5 text-xs font-bold text-muted sm:grid">
                <span>Bahan</span>
                <span>Satuan</span>
                <span>Dipakai di</span>
                <span></span>
            </div>
            <div class="divide-y divide-line">
                @foreach ($ingredients as $ingredient)
                    @php
                        $failed = old('_form') === 'ingredient-row-'.$ingredient->id;
                        $info = $ingredientInfo[$ingredient->id];
                        $usage = collect([
                            $info['products'] ? "resep {$info['products']} produk" : null,
                            $info['periods'] ? "stok {$info['periods']} periode" : null,
                            $info['ordered'] ? 'pesanan' : null,
                        ])->filter();
                    @endphp
                    <details class="group" @if ($failed) open @endif>
                        <summary class="grid cursor-pointer list-none grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-1 px-5 py-3.5 transition hover:bg-oat focus:outline-none focus-visible:bg-oat sm:grid-cols-[minmax(0,2fr)_6rem_minmax(0,2fr)_5rem] sm:items-center [&::-webkit-details-marker]:hidden">
                            <span class="flex min-w-0 items-center gap-2">
                                <span class="truncate font-bold text-charcoal">{{ $ingredient->name }}</span>
                                @if ($ingredient->is_sugar)
                                    <span class="shrink-0 rounded-full bg-amber-soft px-2 py-0.5 text-xs font-bold text-amber-text">Gula</span>
                                @endif
                            </span>
                            <span class="col-start-1 text-sm text-muted sm:col-start-auto">{{ $ingredient->unit }}</span>
                            <span class="col-start-1 text-sm sm:col-start-auto {{ $usage->isEmpty() ? 'text-muted' : 'text-charcoal' }}">
                                {{ $usage->isEmpty() ? 'Belum dipakai' : ucfirst($usage->join(', ')) }}
                            </span>
                            <span class="col-start-2 row-span-3 row-start-1 flex items-center justify-end gap-1 text-sm font-bold text-brand sm:col-start-auto sm:row-span-1 sm:row-start-auto">
                                <span class="group-open:hidden">Ubah</span>
                                <span class="hidden group-open:inline">Tutup</span>
                                <svg class="h-4 w-4 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.2 7.2a.75.75 0 0 1 1.06 0L10 10.94l3.74-3.74a.75.75 0 1 1 1.06 1.06l-4.27 4.27a.75.75 0 0 1-1.06 0L5.2 8.26a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                            </span>
                        </summary>

                        <div class="border-t border-dashed border-line bg-oat/60 px-5 py-4">
                            <form action="{{ route('admin.ingredients.update', $ingredient) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="_form" value="ingredient-row-{{ $ingredient->id }}" />

                                @if ($failed && $errors->any())
                                    <ul class="mb-3 space-y-1 rounded-chip bg-red-50 px-3 py-2 text-sm text-red-700">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                @endif

                                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_7rem_auto_auto] sm:items-end">
                                    <div>
                                        <label for="ingredient_name_{{ $ingredient->id }}" class="mb-1 block text-xs font-bold text-muted">Nama bahan</label>
                                        <input type="text" id="ingredient_name_{{ $ingredient->id }}" name="name" value="{{ $failed ? old('name') : $ingredient->name }}" class="{{ $field }}" />
                                    </div>
                                    <div>
                                        <label for="ingredient_unit_{{ $ingredient->id }}" class="mb-1 block text-xs font-bold text-muted">Satuan</label>
                                        <select id="ingredient_unit_{{ $ingredient->id }}" name="unit" class="{{ $field }}">
                                            <option value="g" @selected(($failed ? old('unit') : $ingredient->unit) === 'g')>gram (g)</option>
                                            <option value="ml" @selected(($failed ? old('unit') : $ingredient->unit) === 'ml')>mililiter (ml)</option>
                                        </select>
                                    </div>
                                    <label class="flex items-center gap-2 py-2 text-sm text-charcoal">
                                        <input type="checkbox" name="is_sugar" value="1" class="h-4 w-4 rounded border-line text-brand focus:ring-brand" @checked($failed ? old('is_sugar') : $ingredient->is_sugar) />
                                        Ini gula
                                    </label>
                                    <button type="submit" class="rounded-brand bg-brand px-4 py-2 text-sm font-bold text-surface transition hover:bg-opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">
                                        Simpan perubahan
                                    </button>
                                </div>
                            </form>

                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-3">
                                @if ($usage->isEmpty())
                                    <p class="text-xs text-muted">Bahan ini belum dipakai di mana pun, jadi aman dihapus.</p>
                                    <form
                                        action="{{ route('admin.ingredients.destroy', $ingredient) }}"
                                        method="POST"
                                        onsubmit="return confirm({{ Illuminate\Support\Js::from('Hapus bahan '.$ingredient->name.'?') }});"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-brand px-3 py-1.5 text-sm font-bold text-red-600 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400">
                                            Hapus bahan
                                        </button>
                                    </form>
                                @else
                                    <p class="text-xs text-muted">Tidak bisa dihapus karena masih dipakai di {{ $usage->join(', ') }}.</p>
                                @endif
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
        @endif
    </div>
</section>
