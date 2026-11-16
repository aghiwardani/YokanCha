{{-- Ukuran: add form, then one row per size (smallest first) that opens into its edit/delete panel. --}}
@php
    $sizeAddOld = fn (string $key) => old('_form') === 'size-new' ? old($key) : null;
    $field = 'w-full rounded-chip border border-line bg-surface px-3 py-2 text-sm text-charcoal focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30';
@endphp
<section id="ukuran" role="tabpanel" aria-labelledby="tab-ukuran" class="scroll-mt-8">
    <p class="mb-4 max-w-xl text-sm leading-6 text-muted">
        Ukuran botol yang bisa dipilih di tiap produk. Isi dalam ml dipakai untuk mengurutkan ukuran dari yang terkecil dan untuk menghitung resep ukuran baru secara otomatis.
    </p>

    <form action="{{ route('admin.sizes.store') }}" method="POST" class="mb-4 rounded-brand border border-line bg-surface p-4">
        @csrf
        <input type="hidden" name="_form" value="size-new" />
        <p class="mb-3 text-sm font-bold text-charcoal">Tambah ukuran</p>

        @if (old('_form') === 'size-new' && $errors->any())
            <ul class="mb-3 space-y-1 rounded-chip bg-red-50 px-3 py-2 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_9rem_auto] sm:items-end">
            <div>
                <label for="new_size_name" class="mb-1 block text-xs font-bold text-muted">Nama ukuran</label>
                <input type="text" id="new_size_name" name="name" placeholder="Contoh: 2 liter" value="{{ $sizeAddOld('name') }}" class="{{ $field }}" />
            </div>
            <div>
                <label for="new_size_volume" class="mb-1 block text-xs font-bold text-muted">Isi (ml)</label>
                <input type="text" inputmode="numeric" data-number="int" id="new_size_volume" name="volume_ml" min="1" placeholder="2000" value="{{ $sizeAddOld('volume_ml') }}" class="{{ $field }}" />
            </div>
            <button type="submit" class="rounded-brand bg-cta px-4 py-2 text-sm font-bold text-surface transition hover:bg-opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-cta focus-visible:ring-offset-2">
                Tambah ukuran
            </button>
        </div>
    </form>

    <div class="overflow-hidden rounded-brand border border-line bg-surface">
        @if ($sizes->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="font-bold text-charcoal">Belum ada ukuran.</p>
                <p class="mt-1 text-sm text-muted">Tambahkan ukuran botol di atas supaya bisa dicentang di produk.</p>
            </div>
        @else
            <div class="hidden grid-cols-[minmax(0,2fr)_7rem_minmax(0,2fr)_5rem] gap-4 border-b border-line bg-oat px-5 py-2.5 text-xs font-bold text-muted sm:grid">
                <span>Ukuran</span>
                <span>Isi</span>
                <span>Dipakai di</span>
                <span></span>
            </div>
            <div class="divide-y divide-line">
                @foreach ($sizes as $size)
                    @php
                        $failed = old('_form') === 'size-row-'.$size->id;
                        $productCount = $sizeInfo[$size->id]['products'];
                    @endphp
                    <details class="group" @if ($failed) open @endif>
                        <summary class="grid cursor-pointer list-none grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-1 px-5 py-3.5 transition hover:bg-oat focus:outline-none focus-visible:bg-oat sm:grid-cols-[minmax(0,2fr)_7rem_minmax(0,2fr)_5rem] sm:items-center [&::-webkit-details-marker]:hidden">
                            <span class="truncate font-bold text-charcoal">{{ $size->name }}</span>
                            <span class="col-start-1 text-sm text-muted sm:col-start-auto">{{ $size->volume_ml > 0 ? number_format($size->volume_ml, 0, ',', '.').' ml' : 'Belum diisi' }}</span>
                            <span class="col-start-1 text-sm sm:col-start-auto {{ $productCount ? 'text-charcoal' : 'text-muted' }}">
                                {{ $productCount ? "{$productCount} produk" : 'Belum dipakai' }}
                            </span>
                            <span class="col-start-2 row-span-3 row-start-1 flex items-center justify-end gap-1 text-sm font-bold text-brand sm:col-start-auto sm:row-span-1 sm:row-start-auto">
                                <span class="group-open:hidden">Ubah</span>
                                <span class="hidden group-open:inline">Tutup</span>
                                <svg class="h-4 w-4 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.2 7.2a.75.75 0 0 1 1.06 0L10 10.94l3.74-3.74a.75.75 0 1 1 1.06 1.06l-4.27 4.27a.75.75 0 0 1-1.06 0L5.2 8.26a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                            </span>
                        </summary>

                        <div class="border-t border-dashed border-line bg-oat/60 px-5 py-4">
                            <form action="{{ route('admin.sizes.update', $size) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="_form" value="size-row-{{ $size->id }}" />

                                @if ($failed && $errors->any())
                                    <ul class="mb-3 space-y-1 rounded-chip bg-red-50 px-3 py-2 text-sm text-red-700">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                @endif

                                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_9rem_auto] sm:items-end">
                                    <div>
                                        <label for="size_name_{{ $size->id }}" class="mb-1 block text-xs font-bold text-muted">Nama ukuran</label>
                                        <input
                                            type="text"
                                            id="size_name_{{ $size->id }}"
                                            name="name"
                                            value="{{ $failed ? old('name') : $size->name }}"
                                            @if ($productCount) readonly aria-describedby="size_name_note_{{ $size->id }}" @endif
                                            class="{{ $field }} {{ $productCount ? 'bg-oat text-muted' : '' }}"
                                        />
                                    </div>
                                    <div>
                                        <label for="size_volume_{{ $size->id }}" class="mb-1 block text-xs font-bold text-muted">Isi (ml)</label>
                                        <input type="text" inputmode="numeric" data-number="int" id="size_volume_{{ $size->id }}" name="volume_ml" min="1" value="{{ $failed ? old('volume_ml') : $size->volume_ml }}" class="{{ $field }}" />
                                    </div>
                                    <button type="submit" class="rounded-brand bg-brand px-4 py-2 text-sm font-bold text-surface transition hover:bg-opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">
                                        Simpan perubahan
                                    </button>
                                </div>
                                @if ($productCount)
                                    <p id="size_name_note_{{ $size->id }}" class="mt-2 text-xs text-muted">Nama tidak bisa diganti karena sudah dipakai produk. Isi ml tetap bisa diubah.</p>
                                @endif
                            </form>

                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-3">
                                @if ($productCount === 0)
                                    <p class="text-xs text-muted">Ukuran ini belum dipakai produk, jadi aman dihapus.</p>
                                    <form
                                        action="{{ route('admin.sizes.destroy', $size) }}"
                                        method="POST"
                                        onsubmit="return confirm({{ Illuminate\Support\Js::from('Hapus ukuran '.$size->name.'?') }});"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-brand px-3 py-1.5 text-sm font-bold text-red-600 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400">
                                            Hapus ukuran
                                        </button>
                                    </form>
                                @else
                                    <p class="text-xs text-muted">Tidak bisa dihapus karena masih dijual di {{ $productCount }} produk. Hilangkan centangnya di produk dulu.</p>
                                @endif
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
        @endif
    </div>
</section>
