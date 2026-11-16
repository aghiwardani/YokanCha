{{-- Tingkat Manis: add form, then one row per level (least sweet first) that opens into its edit/delete panel. --}}
@php
    $sweetAddOld = fn (string $key) => old('_form') === 'sweetness-new' ? old($key) : null;
    $field = 'w-full rounded-chip border border-line bg-surface px-3 py-2 text-sm text-charcoal focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30';
@endphp
<section id="manis" role="tabpanel" aria-labelledby="tab-manis" class="scroll-mt-8">
    <p class="mb-4 max-w-xl text-sm leading-6 text-muted">
        Pilihan manis yang bisa dicentang di tiap produk. Resep ditulis untuk takaran Normal (100%), lalu semua bahan gula dikali persen tingkat manis yang dipilih pelanggan. Contoh: gula 20 ml di resep jadi 10 ml untuk Less sugar 50%.
    </p>

    <form action="{{ route('admin.sweetness.store') }}" method="POST" class="mb-4 rounded-brand border border-line bg-surface p-4">
        @csrf
        <input type="hidden" name="_form" value="sweetness-new" />
        <p class="mb-3 text-sm font-bold text-charcoal">Tambah tingkat manis</p>

        @if (old('_form') === 'sweetness-new' && $errors->any())
            <ul class="mb-3 space-y-1 rounded-chip bg-red-50 px-3 py-2 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_9rem_auto] sm:items-end">
            <div>
                <label for="new_sweetness_name" class="mb-1 block text-xs font-bold text-muted">Nama tingkat manis</label>
                <input type="text" id="new_sweetness_name" name="name" placeholder="Contoh: Sedikit manis" value="{{ $sweetAddOld('name') }}" class="{{ $field }}" />
            </div>
            <div>
                <label for="new_sweetness_percent" class="mb-1 block text-xs font-bold text-muted">Gula (% dari Normal)</label>
                <input type="text" inputmode="numeric" data-number="int" id="new_sweetness_percent" name="percent" placeholder="75" value="{{ $sweetAddOld('percent') }}" class="{{ $field }}" />
            </div>
            <button type="submit" class="rounded-brand bg-cta px-4 py-2 text-sm font-bold text-surface transition hover:bg-opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-cta focus-visible:ring-offset-2">
                Tambah tingkat manis
            </button>
        </div>
    </form>

    <div class="overflow-hidden rounded-brand border border-line bg-surface">
        @if ($sweetnessLevels->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="font-bold text-charcoal">Belum ada tingkat manis.</p>
                <p class="mt-1 text-sm text-muted">Contoh yang umum: Tanpa gula 0%, Less sugar 50%, Manis normal 100%, Extra manis 150%.</p>
            </div>
        @else
            <div class="hidden grid-cols-[minmax(0,2fr)_minmax(0,2fr)_minmax(0,1.5fr)_5rem] gap-4 border-b border-line bg-oat px-5 py-2.5 text-xs font-bold text-muted sm:grid">
                <span>Tingkat manis</span>
                <span>Gula dari takaran Normal</span>
                <span>Dipakai di</span>
                <span></span>
            </div>
            <div class="divide-y divide-line">
                @foreach ($sweetnessLevels as $level)
                    @php
                        $failed = old('_form') === 'sweetness-row-'.$level->id;
                        $productCount = $sweetnessInfo[$level->id]['products'];
                    @endphp
                    <details class="group" @if ($failed) open @endif>
                        <summary class="grid cursor-pointer list-none grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-1 px-5 py-3.5 transition hover:bg-oat focus:outline-none focus-visible:bg-oat sm:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_minmax(0,1.5fr)_5rem] sm:items-center [&::-webkit-details-marker]:hidden">
                            <span class="truncate font-bold text-charcoal">{{ $level->name }}</span>
                            <span class="col-start-1 flex items-center gap-3 sm:col-start-auto">
                                {{-- Bar: 100% = full width of the track, capped at 150% (bar overflow shows as a darker tail). --}}
                                <span class="relative h-2 w-24 shrink-0 overflow-hidden rounded-full bg-oat-deep" aria-hidden="true">
                                    <span class="absolute inset-y-0 left-0 rounded-full {{ $level->percent > 100 ? 'bg-cta' : 'bg-brand' }}" style="width: {{ min(100, round($level->percent / 1.5)) }}%"></span>
                                </span>
                                <span class="text-sm font-bold text-charcoal">{{ $level->percent }}%</span>
                            </span>
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
                            <form action="{{ route('admin.sweetness.update', $level) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="_form" value="sweetness-row-{{ $level->id }}" />

                                @if ($failed && $errors->any())
                                    <ul class="mb-3 space-y-1 rounded-chip bg-red-50 px-3 py-2 text-sm text-red-700">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                @endif

                                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_9rem_auto] sm:items-end">
                                    <div>
                                        <label for="sweetness_name_{{ $level->id }}" class="mb-1 block text-xs font-bold text-muted">Nama tingkat manis</label>
                                        <input
                                            type="text"
                                            id="sweetness_name_{{ $level->id }}"
                                            name="name"
                                            value="{{ $failed ? old('name') : $level->name }}"
                                            @if ($productCount) aria-describedby="sweetness_name_note_{{ $level->id }}" @endif
                                            class="{{ $field }}"
                                        />
                                    </div>
                                    <div>
                                        <label for="sweetness_percent_{{ $level->id }}" class="mb-1 block text-xs font-bold text-muted">Gula (% dari Normal)</label>
                                        <input type="text" inputmode="numeric" data-number="int" id="sweetness_percent_{{ $level->id }}" name="percent" value="{{ $failed ? old('percent') : $level->percent }}" class="{{ $field }}" />
                                    </div>
                                    <button type="submit" class="rounded-brand bg-brand px-4 py-2 text-sm font-bold text-surface transition hover:bg-opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">
                                        Simpan perubahan
                                    </button>
                                </div>
                                @if ($productCount)
                                    <p id="sweetness_name_note_{{ $level->id }}" class="mt-2 text-xs text-muted">Dipakai {{ $productCount }} produk. Kalau namanya diganti, pilihan manis di produk itu ikut berganti nama. Perubahan persen langsung berlaku untuk pesanan berikutnya; pesanan lama tetap seperti saat dipesan.</p>
                                @endif
                            </form>

                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-3">
                                @if ($productCount === 0)
                                    <p class="text-xs text-muted">Tingkat manis ini belum dipakai produk, jadi aman dihapus.</p>
                                    <form
                                        action="{{ route('admin.sweetness.destroy', $level) }}"
                                        method="POST"
                                        onsubmit="return confirm({{ Illuminate\Support\Js::from('Hapus tingkat manis '.$level->name.'?') }});"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-brand px-3 py-1.5 text-sm font-bold text-red-600 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400">
                                            Hapus tingkat manis
                                        </button>
                                    </form>
                                @else
                                    <p class="text-xs text-muted">Tidak bisa dihapus karena masih ditawarkan di {{ $productCount }} produk. Hilangkan centangnya di produk dulu.</p>
                                @endif
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
        @endif
    </div>
</section>
