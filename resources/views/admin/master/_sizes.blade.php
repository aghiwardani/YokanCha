{{-- Ukuran: add form + one edit/delete row per size, smallest first. --}}
@php
    $sizeAddOld = fn (string $key) => old('_form') === 'size-new' ? old($key) : null;
@endphp
<section id="ukuran" class="space-y-8 scroll-mt-8">
    <h2 class="text-2xl font-bold text-charcoal">Ukuran</h2>

    <div class="bg-surface rounded-brand shadow-sm p-8">
        <h3 class="text-xl font-bold text-charcoal mb-6">Tambah Ukuran</h3>

        <form action="{{ route('admin.sizes.store') }}" method="POST" class="space-y-6">
            @csrf
            <input type="hidden" name="_form" value="size-new" />

            @if (old('_form') === 'size-new' && $errors->any())
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
                    <label for="new_size_name" class="block text-sm font-semibold text-charcoal mb-2">Nama Ukuran</label>
                    <input
                        type="text"
                        id="new_size_name"
                        name="name"
                        placeholder="Contoh: 500 ml"
                        value="{{ $sizeAddOld('name') }}"
                        class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                    />
                </div>
                <div>
                    <label for="new_size_volume" class="block text-sm font-semibold text-charcoal mb-2">Isi (ml)</label>
                    <input
                        type="number"
                        id="new_size_volume"
                        name="volume_ml"
                        min="1"
                        placeholder="500"
                        value="{{ $sizeAddOld('volume_ml') }}"
                        class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                    />
                </div>
            </div>

            <p class="text-xs text-charcoal opacity-75">Isi dalam ml dipakai untuk mengurutkan ukuran dari yang terkecil dan untuk mengisi resep ukuran baru secara otomatis (1 liter = 1000).</p>

            <button
                type="submit"
                class="w-full bg-cta hover:bg-opacity-90 text-surface font-semibold py-3 px-6 rounded-brand transition duration-200"
            >
                Tambah Ukuran
            </button>
        </form>
    </div>

    <div class="bg-surface rounded-brand shadow-sm p-8">
        <h3 class="text-xl font-bold text-charcoal mb-6">Daftar Ukuran</h3>

        @if ($sizes->isEmpty())
            <p class="text-charcoal opacity-75">Belum ada ukuran.</p>
        @else
            <div class="space-y-4">
                @foreach ($sizes as $size)
                    @php $failed = old('_form') === 'size-row-'.$size->id; @endphp
                    <div class="flex flex-wrap items-center gap-3 border-2 border-brand rounded-brand p-4">
                        <form
                            action="{{ route('admin.sizes.update', $size) }}"
                            method="POST"
                            class="flex flex-1 flex-wrap items-center gap-3"
                        >
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_form" value="size-row-{{ $size->id }}" />

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
                                value="{{ $failed ? old('name') : $size->name }}"
                                aria-label="Nama ukuran"
                                class="flex-1 min-w-[10rem] px-3 py-2 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                            />
                            <label class="flex items-center gap-2 text-sm text-charcoal">
                                <input
                                    type="number"
                                    name="volume_ml"
                                    min="1"
                                    value="{{ $failed ? old('volume_ml') : $size->volume_ml }}"
                                    aria-label="Isi (ml)"
                                    class="w-28 px-3 py-2 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                                />
                                ml
                            </label>
                            <button
                                type="submit"
                                class="px-4 py-2 bg-brand text-surface font-semibold rounded-brand hover:bg-opacity-90 transition duration-200"
                            >
                                Simpan
                            </button>
                        </form>
                        <form
                            action="{{ route('admin.sizes.destroy', $size) }}"
                            method="POST"
                            onsubmit="return confirm({{ Illuminate\Support\Js::from('Hapus ukuran '.$size->name.'?') }});"
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
