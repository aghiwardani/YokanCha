@extends('layouts.admin')

@section('title', 'Tambah Pesanan - YokanCha')

@php
    $field = 'w-full px-3 py-2 bg-surface border-2 border-brand rounded-brand text-sm focus:outline-none focus:ring-2 focus:ring-cta';
    $label = 'block text-xs font-semibold text-muted mb-1';
    $oldLines = array_values(old('lines', []));
    $rowCount = max(3, count($oldLines));
    $statusLabel = ['open' => 'buka', 'closed' => 'tutup', 'draft' => 'draf'];
@endphp

@section('admin-content')
<div class="min-h-screen bg-oat py-8 px-4 sm:py-10">
    <div class="max-w-3xl mx-auto space-y-6">
        <div>
            <a href="{{ route('admin.orders', array_filter(['po_period_id' => $period?->id])) }}" class="text-sm font-semibold text-brand underline">Kembali ke pesanan</a>
            <h1 class="mt-2 text-3xl font-bold text-charcoal">Tambah pesanan</h1>
            <p class="mt-1 text-sm text-muted">
                Untuk pesanan yang masuk di luar web, misalnya setelah PO tutup. Bisa untuk periode yang sudah tutup, dan stok bahannya tetap terpotong.
            </p>
        </div>

        @if ($periods->isEmpty())
            <div class="bg-surface rounded-brand border border-line p-8 text-center text-charcoal">Belum ada periode. Buat periode PO dulu.</div>
        @else
            {{-- Choosing a period reloads the menu of that period. --}}
            <form method="GET" action="{{ route('admin.orders.create') }}" class="bg-surface rounded-brand border border-line p-5">
                <label for="period" class="{{ $label }}">Periode</label>
                <select id="period" name="period" class="{{ $field }}" onchange="this.form.submit()">
                    @foreach ($periods as $p)
                        <option value="{{ $p->id }}" @selected($p->id === $period->id)>{{ $p->name }} ({{ $statusLabel[$p->status] ?? $p->status }})</option>
                    @endforeach
                </select>
            </form>

            @if (! $menu)
                <div class="bg-surface rounded-brand border border-line p-8 text-center text-charcoal">
                    Periode ini belum punya menu. Centang menunya di
                    <a href="{{ route('admin.periods.stock', $period) }}" class="font-semibold text-brand underline">Stok bahan</a>.
                </div>
            @else
                <form method="POST" action="{{ route('admin.orders.store') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="po_period_id" value="{{ $period->id }}">

                    @if ($errors->any())
                        <ul class="p-4 rounded-brand bg-red-50 text-sm text-red-700 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <section class="bg-surface rounded-brand border border-line p-5 space-y-4">
                        <h2 class="text-lg font-bold text-charcoal">Pemesan</h2>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="customer_name" class="{{ $label }}">Nama</label>
                                <input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" class="{{ $field }}">
                            </div>
                            <div>
                                <label for="customer_whatsapp" class="{{ $label }}">Nomor WhatsApp</label>
                                <input id="customer_whatsapp" name="customer_whatsapp" type="tel" inputmode="tel" placeholder="081234567890" value="{{ old('customer_whatsapp') }}" class="{{ $field }}">
                            </div>
                        </div>
                    </section>

                    <section class="bg-surface rounded-brand border border-line p-5 space-y-3">
                        <h2 class="text-lg font-bold text-charcoal">Menu</h2>
                        <p class="text-xs text-muted">Harga terisi otomatis sesuai ukuran. Ubah kalau ada potongan harga, isi 0 kalau gratis. Baris kosong tidak disimpan.</p>

                        <div class="space-y-3" data-lines>
                            @for ($i = 0; $i < $rowCount; $i++)
                                @php $old = $oldLines[$i] ?? []; @endphp
                                <div class="grid grid-cols-2 gap-2 rounded-brand border border-line p-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_5rem_7rem]" data-line
                                     data-old-size="{{ $old['size'] ?? '' }}" data-old-sweetness="{{ $old['sweetness'] ?? '' }}" data-old-price="{{ $old['price'] ?? '' }}">
                                    <div class="col-span-2 sm:col-span-1">
                                        <label class="{{ $label }}">Menu</label>
                                        <select name="lines[{{ $i }}][ppp]" class="{{ $field }}" data-ppp>
                                            <option value="">Pilih menu</option>
                                            @foreach ($menu as $item)
                                                <option value="{{ $item['ppp'] }}" @selected((string) ($old['ppp'] ?? '') === (string) $item['ppp'])>{{ $item['name'] }}{{ $item['active'] ? '' : ' (nonaktif)' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="{{ $label }}">Ukuran</label>
                                        <select name="lines[{{ $i }}][size]" class="{{ $field }}" data-size></select>
                                    </div>
                                    <div>
                                        <label class="{{ $label }}">Manis</label>
                                        <select name="lines[{{ $i }}][sweetness]" class="{{ $field }}" data-sweetness></select>
                                    </div>
                                    <div>
                                        <label class="{{ $label }}">Botol</label>
                                        <input name="lines[{{ $i }}][qty]" type="number" min="1" max="999" value="{{ $old['qty'] ?? '' }}" class="{{ $field }}" data-qty>
                                    </div>
                                    <div>
                                        <label class="{{ $label }}">Harga/botol</label>
                                        <input name="lines[{{ $i }}][price]" type="text" inputmode="numeric" data-number="int" class="{{ $field }}" data-price>
                                    </div>
                                </div>
                            @endfor
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <button type="button" class="text-sm font-semibold text-brand underline" data-add-line>+ Tambah baris</button>
                            <p class="text-sm text-charcoal">Total: <strong data-grand-total>Rp0</strong></p>
                        </div>
                    </section>

                    <section class="bg-surface rounded-brand border border-line p-5 space-y-4">
                        <h2 class="text-lg font-bold text-charcoal">Status</h2>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="payment_status" class="{{ $label }}">Pembayaran</label>
                                <select id="payment_status" name="payment_status" class="{{ $field }}">
                                    <option value="unpaid" @selected(old('payment_status', 'unpaid') === 'unpaid')>Belum bayar</option>
                                    <option value="paid" @selected(old('payment_status') === 'paid')>Sudah bayar</option>
                                </select>
                            </div>
                            <div>
                                <label for="order_status" class="{{ $label }}">Status pesanan</label>
                                <select id="order_status" name="order_status" class="{{ $field }}">
                                    @foreach (['new' => 'Baru', 'diproses' => 'Diproses', 'siap_diambil' => 'Siap diambil', 'selesai' => 'Selesai'] as $value => $text)
                                        <option value="{{ $value }}" @selected(old('order_status', 'new') === $value)>{{ $text }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <label class="flex items-start gap-2 text-sm text-charcoal">
                            <input type="checkbox" name="allow_short" value="1" class="mt-0.5 h-4 w-4 rounded border-line text-brand" @checked(old('allow_short'))>
                            <span>
                                Tetap simpan walau stok bahan kurang
                                <span class="block text-xs text-muted">Pakai kalau botolnya memang sudah dibuat. Stok terpakai akan melebihi stok, dan terlihat di halaman Stok bahan.</span>
                            </span>
                        </label>
                    </section>

                    <button type="submit" class="w-full rounded-brand bg-cta px-6 py-3 font-semibold text-surface hover:bg-opacity-90">Simpan pesanan</button>
                </form>

                <script>
                    (() => {
                        const menu = @json(collect($menu)->keyBy('ppp'));
                        const list = document.querySelector('[data-lines]');
                        const rupiah = (n) => 'Rp' + Math.round(n).toLocaleString('id-ID');
                        const plain = (v) => Number(String(v || '').replace(/\./g, '')) || 0;

                        const fill = (select, options, keep) => {
                            select.innerHTML = '';
                            options.forEach((name) => {
                                const opt = new Option(name === '-' ? 'Standar' : name, name);
                                select.add(opt);
                            });
                            if (keep && options.includes(keep)) select.value = keep;
                        };

                        const total = () => {
                            let sum = 0;
                            list.querySelectorAll('[data-line]').forEach((line) => {
                                sum += plain(line.querySelector('[data-price]').value) * (Number(line.querySelector('[data-qty]').value) || 0);
                            });
                            document.querySelector('[data-grand-total]').textContent = rupiah(sum);
                        };

                        const setPrice = (line) => {
                            const item = menu[line.querySelector('[data-ppp]').value];
                            const price = line.querySelector('[data-price]');
                            if (!item || price.dataset.byHand === '1') return;
                            const value = item.sizes[line.querySelector('[data-size]').value];
                            price.value = value === undefined ? '' : Number(value).toLocaleString('id-ID');
                        };

                        const setup = (line, first) => {
                            const item = menu[line.querySelector('[data-ppp]').value];
                            const size = line.querySelector('[data-size]');
                            const sweet = line.querySelector('[data-sweetness]');
                            const price = line.querySelector('[data-price]');
                            if (!item) {
                                size.innerHTML = ''; sweet.innerHTML = '';
                                if (!first) { price.value = ''; price.dataset.byHand = ''; }
                                total();
                                return;
                            }
                            fill(size, Object.keys(item.sizes), first ? line.dataset.oldSize : size.value);
                            fill(sweet, item.sweetness, first ? line.dataset.oldSweetness : (sweet.value || (item.sweetness.includes('Normal') ? 'Normal' : '')));
                            if (first && line.dataset.oldPrice !== '') {
                                price.value = Number(line.dataset.oldPrice).toLocaleString('id-ID');
                                price.dataset.byHand = '1';
                            } else {
                                setPrice(line);
                            }
                            if (!line.querySelector('[data-qty]').value) line.querySelector('[data-qty]').value = 1;
                            total();
                        };

                        list.addEventListener('change', (e) => {
                            const line = e.target.closest('[data-line]');
                            if (!line) return;
                            if (e.target.matches('[data-ppp]')) { line.querySelector('[data-price]').dataset.byHand = ''; setup(line, false); }
                            if (e.target.matches('[data-size]')) { setPrice(line); total(); }
                        });
                        list.addEventListener('input', (e) => {
                            if (e.target.matches('[data-price]')) e.target.dataset.byHand = e.target.value === '' ? '' : '1';
                            if (e.target.matches('[data-price], [data-qty]')) total();
                        });

                        document.querySelector('[data-add-line]').addEventListener('click', () => {
                            const lines = list.querySelectorAll('[data-line]');
                            const copy = lines[0].cloneNode(true);
                            const index = lines.length;
                            copy.querySelectorAll('[name]').forEach((el) => { el.name = el.name.replace(/lines\[\d+\]/, `lines[${index}]`); });
                            copy.querySelector('[data-ppp]').value = '';
                            copy.querySelector('[data-qty]').value = '';
                            copy.dataset.oldSize = copy.dataset.oldSweetness = copy.dataset.oldPrice = '';
                            list.appendChild(copy);
                            setup(copy, false);
                        });

                        list.querySelectorAll('[data-line]').forEach((line) => setup(line, true));
                    })();
                </script>
            @endif
        @endif
    </div>
</div>
@endsection
