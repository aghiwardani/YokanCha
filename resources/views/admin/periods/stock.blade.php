@extends('layouts.admin')

@section('title', 'Stok Bahan - YokanCha')

@section('admin-content')
@php
    // Show 30 instead of 30.00, keep 0.5 as 0.5.
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    $hasOld = old('_form') === 'stock';
@endphp
<div class="min-h-screen bg-oat py-8 px-4 sm:py-12">
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

            @php
                $rp = fn ($n) => 'Rp'.number_format((int) round($n), 0, ',', '.');
                $nice = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
                // Plain digits for [data-number] fields; the page script adds the dots.
                $plainInt = fn ($v) => ($v === null || $v === '') ? '' : (string) (int) \App\Support\Number::normalize((string) $v);
                $plainDec = fn ($v) => ($v === null || $v === '') ? '' : $fmt(\App\Support\Number::normalize((string) $v));
                $field = 'w-full px-3 py-2 border-2 border-brand rounded-brand bg-surface focus:outline-none focus:ring-2 focus:ring-cta';
            @endphp
            <div class="bg-surface rounded-brand shadow-sm p-5 sm:p-8">
                <h2 class="text-xl font-bold text-charcoal">Stok per Bahan</h2>
                <p class="mt-1 mb-6 text-sm text-muted">
                    Stok periode ini = sisa yang masih bagus dari periode lalu + belanja baru.
                    Harga belanja terisi otomatis dari harga per kemasan di master Bahan, dan tetap bisa kamu ubah.
                </p>

                @if ($prefilled)
                    <p class="mb-4 rounded-brand bg-sage-soft px-4 py-3 text-sm text-charcoal">
                        Sisa bahan dari {{ $previous->name }} sudah terisi. Kurangi atau isi 0 untuk bahan yang basi, lalu klik Simpan.
                    </p>
                @endif

                @if ($ingredients->isEmpty())
                    <p class="text-charcoal opacity-75">Belum ada bahan. Tambah dulu di halaman Bahan.</p>
                @else
                    <div class="space-y-4">
                        @foreach ($ingredients as $ingredient)
                            @php
                                $id = $ingredient->id;
                                $row = $rows->get($id);
                                $left = $leftovers[$id] ?? null;
                                $showCarried = $left || ($row && (float) $row->carried > 0);
                                $carriedValue = $hasOld ? old('carried.'.$id) : $carried[$id];
                                $buyValue = $hasOld ? old('buy.'.$id) : $bought[$id];
                                $buyCostValue = $hasOld ? old('buy_cost.'.$id) : $boughtCost[$id];
                                // A saved purchase of 0 shows as empty, not "0".
                                if (! $hasOld && $row && (float) $buyValue <= 0) { $buyValue = null; $buyCostValue = null; }
                                $unitPrice = $ingredient->unitPrice();
                                $unitCost = $row?->unitCost();
                                $spoiled = $left ? max(0, $left['amount'] - (float) ($row ? $row->carried : $carriedValue)) : 0;
                            @endphp
                            <div class="border-2 border-brand rounded-brand p-4 space-y-3" data-stock-row data-unit-price="{{ $unitPrice ?? '' }}" data-left="{{ $left['amount'] ?? 0 }}">
                                <p class="font-semibold text-charcoal">
                                    {{ $ingredient->name }} <span class="font-normal opacity-75">({{ $ingredient->unit }})</span>
                                </p>

                                @if ($showCarried)
                                    <div class="rounded-brand bg-oat p-3">
                                        <div class="flex flex-wrap items-end gap-3">
                                            <div class="flex-1 min-w-[10rem]">
                                                <label for="carried_{{ $id }}" class="block text-xs font-semibold text-muted mb-1">
                                                    Sisa periode lalu yang dipakai ({{ $ingredient->unit }})
                                                </label>
                                                <input type="text" inputmode="decimal" data-number id="carried_{{ $id }}" name="carried[{{ $id }}]"
                                                       value="{{ $plainDec($carriedValue) }}" data-carried
                                                       class="{{ $field }} @error('carried.'.$id) border-red-500 @enderror" />
                                            </div>
                                            @if ($left)
                                                <button type="button" data-spoil class="px-3 py-2 rounded-full border border-cta text-xs font-semibold text-amber-text hover:bg-amber-soft">Basi semua</button>
                                            @endif
                                        </div>
                                        <p class="mt-2 text-xs text-muted">
                                            @if ($left)
                                                Sisa di {{ $previous->name }}: {{ $nice($left['amount']) }} {{ $ingredient->unit }}{{ $left['unit'] !== null ? ', senilai '.$rp($left['amount'] * $left['unit']) : '' }}.
                                                @if ($spoiled > 0.001)
                                                    <span class="font-semibold text-amber-text">{{ $nice($spoiled) }} {{ $ingredient->unit }} tidak dipakai (basi).</span>
                                                @endif
                                            @else
                                                Sisa dari periode lalu yang sudah tersimpan.
                                            @endif
                                        </p>
                                    </div>
                                @endif

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="buy_{{ $id }}" class="block text-xs font-semibold text-muted mb-1">Belanja baru ({{ $ingredient->unit }})</label>
                                        <input type="text" inputmode="decimal" data-number id="buy_{{ $id }}" name="buy[{{ $id }}]"
                                               value="{{ $plainDec($buyValue) }}" data-buy
                                               class="{{ $field }} @error('buy.'.$id) border-red-500 @enderror" />
                                    </div>
                                    <div>
                                        <label for="buy_cost_{{ $id }}" class="block text-xs font-semibold text-muted mb-1">Harga belanja baru (Rp)</label>
                                        <input type="text" inputmode="numeric" data-number="int" id="buy_cost_{{ $id }}" name="buy_cost[{{ $id }}]"
                                               value="{{ $plainInt($buyCostValue) }}" data-buy-cost
                                               placeholder="{{ $unitPrice !== null ? 'Otomatis: '.$rp($ingredient->pack_price).' / '.$nice($ingredient->pack_size).' '.$ingredient->unit : 'Total harga belanjanya' }}"
                                               class="{{ $field }} @error('buy_cost.'.$id) border-red-500 @enderror" />
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm text-charcoal">
                                    <span class="font-semibold">Total stok: <span data-total>{{ $nice($row?->stock ?? 0) }}</span> {{ $ingredient->unit }}</span>
                                    <span>Terpakai: {{ $nice($row?->used ?? 0) }} {{ $ingredient->unit }}</span>
                                    <span>Sisa: {{ $nice($row?->remaining() ?? 0) }} {{ $ingredient->unit }}</span>
                                    @if ($unitCost !== null)
                                        <span class="text-muted">Rp{{ $nice($unitCost) }} per {{ $ingredient->unit }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <script>
                // Stock rows: live total, "Basi semua", and the purchase price from
                // the master price until it is typed by hand.
                (() => {
                    const plain = (v) => Number(String(v || '').replace(/\./g, '').replace(',', '.')) || 0;
                    const show = (n) => (Math.round(n * 100) / 100).toLocaleString('id-ID');
                    document.querySelectorAll('[data-stock-row]').forEach((row) => {
                        const carried = row.querySelector('[data-carried]');
                        const buy = row.querySelector('[data-buy]');
                        const cost = row.querySelector('[data-buy-cost]');
                        const total = row.querySelector('[data-total]');
                        const unitPrice = Number(row.dataset.unitPrice) || 0;
                        let costByHand = cost.value !== '';

                        const update = () => {
                            total.textContent = show(plain(carried?.value) + plain(buy.value));
                            if (!costByHand && unitPrice > 0) {
                                const amount = plain(buy.value);
                                cost.value = amount > 0 ? Math.round(amount * unitPrice).toLocaleString('id-ID') : '';
                            }
                        };
                        cost.addEventListener('input', () => { costByHand = cost.value !== ''; });
                        carried?.addEventListener('input', update);
                        buy.addEventListener('input', update);
                        row.querySelector('[data-spoil]')?.addEventListener('click', () => { carried.value = '0'; update(); });
                        update();
                    });
                })();
            </script>

            @php
                $lines = $hasOld ? array_values(old('extra', [])) : $costLines;
                $lines = array_values(array_filter($lines, fn ($l) => trim((string) ($l['name'] ?? '')) !== '' || (string) ($l['amount'] ?? '') !== ''));
                $blank = ['name' => '', 'type' => 'per_bottle', 'size' => null, 'amount' => ''];
                $lines = array_merge($lines, array_fill(0, max(2, 4 - count($lines)), $blank));
                $field = 'w-full px-3 py-2 border-2 border-brand rounded-brand bg-surface focus:outline-none focus:ring-2 focus:ring-cta';
            @endphp
            <div class="bg-surface rounded-brand shadow-sm p-5 sm:p-8">
                <h2 class="text-xl font-bold text-charcoal">Biaya lain</h2>
                <p class="mt-1 mb-6 text-sm text-muted">
                    Biaya selain bahan resep yang ikut dihitung di HPP (COGS). <strong>Per botol</strong> untuk kemasan dan label, atau upah per botol.
                    <strong>Per batch</strong> untuk es, gas, ongkos belanja, atau upah sekali produksi. Biaya per batch dibagi rata ke semua botol.
                </p>
                <input type="hidden" name="extra_sent" value="1">

                <div class="space-y-3" data-cost-lines>
                    @foreach ($lines as $i => $line)
                        <div class="grid grid-cols-1 sm:grid-cols-[1fr_8rem_9rem_9rem] gap-2 rounded-brand border border-line p-3" data-cost-line>
                            <div>
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
                            <div>
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

            <div class="bg-surface rounded-brand shadow-sm p-5 sm:p-8">
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
