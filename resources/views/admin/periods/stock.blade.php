@extends('layouts.admin')

@section('title', 'Stok Bahan - YokanCha')

@section('admin-content')
@php
    // Show 30 instead of 30.00, keep 0.5 as 0.5.
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    $hasOld = old('_form') === 'stock';
@endphp
<div class="min-h-screen bg-oat py-12 px-4">
    <div class="max-w-3xl mx-auto space-y-8">
        <div>
            <a href="{{ route('admin.periods') }}" class="text-sm font-semibold text-charcoal underline">&larr; Periode PO</a>
            <h1 class="text-3xl font-bold text-charcoal mt-2">Stok Bahan</h1>
            <p class="text-charcoal opacity-75">{{ $period->name }}</p>
        </div>

        @if (session('status'))
            <div class="p-4 bg-brand bg-opacity-10 border-2 border-brand rounded-brand text-charcoal text-sm">
                {{ session('status') }}
            </div>
        @endif

        <form action="{{ route('admin.periods.stock.update', $period) }}" method="POST" class="space-y-8">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="stock" />

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
                <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                    <h2 class="text-xl font-bold text-charcoal">Stok per Bahan</h2>
                    <a
                        href="{{ route('admin.periods.stock', ['period' => $period, 'prefill' => 'previous']) }}"
                        class="text-sm font-semibold text-charcoal underline"
                    >Isi dari sisa periode lalu</a>
                </div>

                <p class="mb-4 text-sm text-muted">
                    Isi harga beli total untuk stok yang kamu masukkan, misalnya 2.000 ml susu seharga Rp40.000. Harganya dipakai untuk menghitung HPP dan untung di halaman Produksi.
                </p>

                @if ($prefilled)
                    <p class="mb-4 text-sm text-charcoal opacity-75">Terisi dari sisa periode lalu. Belum tersimpan sampai kamu klik Simpan.</p>
                @endif

                @if ($ingredients->isEmpty())
                    <p class="text-charcoal opacity-75">Belum ada bahan. Tambah dulu di halaman Bahan.</p>
                @else
                    <div class="space-y-4">
                        @foreach ($ingredients as $ingredient)
                            @php
                                $row = $rows->get($ingredient->id);
                                $value = $hasOld ? old('stock.'.$ingredient->id) : $values[$ingredient->id];
                                $value = ($value === null || $value === '') ? '' : $fmt($value);
                            @endphp
                            @php
                                $cost = $hasOld ? old('cost.'.$ingredient->id) : ($costs[$ingredient->id] ?? null);
                                // Plain digits ("80000"); the page script adds the thousand dots.
                                $cost = ($cost === null || $cost === '') ? '' : (string) (int) \App\Support\Number::normalize((string) $cost);
                                $unitCost = $row?->unitCost();
                            @endphp
                            <div class="border-2 border-brand rounded-brand p-4 space-y-3">
                                <p class="font-semibold text-charcoal">
                                    {{ $ingredient->name }} <span class="font-normal opacity-75">({{ $ingredient->unit }})</span>
                                </p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="stock_{{ $ingredient->id }}" class="block text-xs font-semibold text-muted mb-1">Stok ({{ $ingredient->unit }})</label>
                                        <input
                                            type="text" inputmode="decimal" data-number
                                            id="stock_{{ $ingredient->id }}"
                                            name="stock[{{ $ingredient->id }}]"
                                            value="{{ $value }}"
                                            class="w-full px-3 py-2 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('stock.'.$ingredient->id) border-red-500 @enderror"
                                        />
                                    </div>
                                    <div>
                                        <label for="cost_{{ $ingredient->id }}" class="block text-xs font-semibold text-muted mb-1">Harga beli total (Rp)</label>
                                        <input
                                            type="text" inputmode="numeric" data-number="int"
                                            id="cost_{{ $ingredient->id }}"
                                            name="cost[{{ $ingredient->id }}]"
                                            value="{{ $cost }}"
                                            placeholder="Untuk seluruh stok ini"
                                            class="w-full px-3 py-2 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('cost.'.$ingredient->id) border-red-500 @enderror"
                                        />
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm text-charcoal">
                                    <span>Terpakai: {{ $fmt($row?->used ?? 0) }} {{ $ingredient->unit }}</span>
                                    <span>Sisa: {{ $fmt($row?->remaining() ?? 0) }} {{ $ingredient->unit }}</span>
                                    @if ($unitCost !== null)
                                        <span class="text-muted">Rp{{ rtrim(rtrim(number_format($unitCost, 2, ',', '.'), '0'), ',') }} per {{ $ingredient->unit }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @php
                $lines = $hasOld ? array_values(old('extra', [])) : $costLines;
                $lines = array_values(array_filter($lines, fn ($l) => trim((string) ($l['name'] ?? '')) !== '' || (string) ($l['amount'] ?? '') !== ''));
                $blank = ['name' => '', 'type' => 'per_bottle', 'size' => null, 'amount' => ''];
                $lines = array_merge($lines, array_fill(0, max(2, 4 - count($lines)), $blank));
                $field = 'w-full px-3 py-2 border-2 border-brand rounded-brand bg-surface focus:outline-none focus:ring-2 focus:ring-cta';
            @endphp
            <div class="bg-surface rounded-brand shadow-sm p-8">
                <h2 class="text-xl font-bold text-charcoal">Biaya lain</h2>
                <p class="mt-1 mb-6 text-sm text-muted">
                    Biaya selain bahan resep yang ikut dihitung di HPP (COGS). <strong>Per botol</strong> untuk kemasan dan label, atau upah per botol.
                    <strong>Per batch</strong> untuk es, gas, ongkos belanja, atau upah sekali produksi. Biaya per batch dibagi rata ke semua botol.
                </p>
                <input type="hidden" name="extra_sent" value="1">

                <div class="space-y-3" data-cost-lines>
                    @foreach ($lines as $i => $line)
                        <div class="grid grid-cols-2 sm:grid-cols-[1fr_8rem_9rem_9rem] gap-2 rounded-brand border border-line p-3" data-cost-line>
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-xs font-semibold text-muted mb-1" for="extra_{{ $i }}_name">Nama biaya</label>
                                <input id="extra_{{ $i }}_name" type="text" name="extra[{{ $i }}][name]" value="{{ $line['name'] ?? '' }}" placeholder="Botol + tutup" class="{{ $field }} @error('extra.'.$i.'.name') border-red-500 @enderror">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-muted mb-1" for="extra_{{ $i }}_type">Jenis</label>
                                <select id="extra_{{ $i }}_type" name="extra[{{ $i }}][type]" class="{{ $field }}" data-cost-type>
                                    <option value="per_bottle" @selected(($line['type'] ?? 'per_bottle') === 'per_bottle')>Per botol</option>
                                    <option value="per_batch" @selected(($line['type'] ?? '') === 'per_batch')>Per batch</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-muted mb-1" for="extra_{{ $i }}_size">Ukuran</label>
                                <select id="extra_{{ $i }}_size" name="extra[{{ $i }}][size]" class="{{ $field }}" data-cost-size @disabled(($line['type'] ?? 'per_bottle') === 'per_batch')>
                                    <option value="">Semua ukuran</option>
                                    @foreach (array_unique(array_filter(array_merge($sizes, [$line['size'] ?? null]))) as $size)
                                        <option value="{{ $size }}" @selected(($line['size'] ?? null) === $size)>{{ $size }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-xs font-semibold text-muted mb-1" for="extra_{{ $i }}_amount">Nominal (Rp)</label>
                                <input id="extra_{{ $i }}_amount" type="text" inputmode="numeric" data-number="int" name="extra[{{ $i }}][amount]" value="{{ ($line['amount'] ?? '') === '' ? '' : (int) \App\Support\Number::normalize((string) $line['amount']) }}" class="{{ $field }} @error('extra.'.$i.'.amount') border-red-500 @enderror">
                            </div>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="mt-3 text-sm font-semibold text-brand underline" data-cost-add>+ Tambah baris</button>
                <p class="mt-2 text-xs text-muted">Kosongkan nama dan nominal untuk menghapus baris.</p>
            </div>

            <script>
                (() => {
                    const list = document.querySelector('[data-cost-lines]');
                    const sync = (line) => {
                        const size = line.querySelector('[data-cost-size]');
                        size.disabled = line.querySelector('[data-cost-type]').value === 'per_batch';
                        if (size.disabled) size.value = '';
                    };
                    list.addEventListener('change', (e) => {
                        if (e.target.matches('[data-cost-type]')) sync(e.target.closest('[data-cost-line]'));
                    });
                    document.querySelector('[data-cost-add]').addEventListener('click', () => {
                        const lines = list.querySelectorAll('[data-cost-line]');
                        const copy = lines[lines.length - 1].cloneNode(true);
                        const index = lines.length;
                        copy.querySelectorAll('[name], [id], label[for]').forEach((el) => {
                            if (el.name) el.name = el.name.replace(/extra\[\d+\]/, `extra[${index}]`);
                            if (el.id) el.id = el.id.replace(/extra_\d+_/, `extra_${index}_`);
                            if (el.htmlFor) el.htmlFor = el.htmlFor.replace(/extra_\d+_/, `extra_${index}_`);
                        });
                        copy.querySelectorAll('input').forEach((el) => { el.value = ''; el.classList.remove('border-red-500'); });
                        copy.querySelector('[data-cost-type]').value = 'per_bottle';
                        sync(copy);
                        list.appendChild(copy);
                        copy.querySelector('input').focus();
                    });
                })();
            </script>

            <div class="bg-surface rounded-brand shadow-sm p-8">
                <h2 class="text-xl font-bold text-charcoal mb-6">Menu Periode Ini</h2>

                @if ($products->isEmpty())
                    <p class="text-charcoal opacity-75">Belum ada menu.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($products as $product)
                            <label class="flex items-center gap-2 text-charcoal">
                                <input
                                    type="checkbox"
                                    name="products[]"
                                    value="{{ $product->id }}"
                                    @checked($hasOld ? in_array($product->id, array_map('intval', old('products', []))) : in_array($product->id, $selectedProducts))
                                />
                                {{ $product->name }}
                                @unless ($product->is_active)
                                    <span class="rounded-full bg-oat-deep px-2 py-0.5 text-xs font-bold text-muted">Nonaktif, tidak tampil di menu</span>
                                @endunless
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            <button
                type="submit"
                class="w-full bg-cta hover:bg-opacity-90 text-surface font-semibold py-3 px-6 rounded-brand transition duration-200"
            >
                Simpan
            </button>
        </form>
    </div>
</div>
@endsection
