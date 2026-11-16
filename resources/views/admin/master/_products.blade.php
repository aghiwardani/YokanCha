{{-- Produk: what's on the menu. Adding and editing use the product form page (it holds the recipe grid). --}}
@php
    $rupiah = fn (int $amount) => 'Rp'.number_format($amount, 0, ',', '.');
@endphp
<section id="produk" role="tabpanel" aria-labelledby="tab-produk" class="scroll-mt-8">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
        <p class="max-w-xl text-sm leading-6 text-muted">
            Menu yang bisa dijual. Harga per ukuran, tingkat manis, dan resepnya diatur di halaman tiap produk.
        </p>
        <a
            href="{{ route('admin.products.create') }}"
            class="inline-flex items-center gap-2 rounded-brand bg-cta px-4 py-2.5 text-sm font-bold text-surface transition hover:bg-opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-cta focus-visible:ring-offset-2"
        >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 0 1 1 1v4h4a1 1 0 1 1 0 2h-4v4a1 1 0 1 1-2 0v-4H5a1 1 0 1 1 0-2h4V5a1 1 0 0 1 1-1Z"/></svg>
            Tambah produk
        </a>
    </div>

    <div class="overflow-hidden rounded-brand border border-line bg-surface">
        @if ($products->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="font-bold text-charcoal">Belum ada produk.</p>
                <p class="mt-1 text-sm text-muted">Tambah produk pertama, lalu centang ukurannya dan isi resepnya.</p>
            </div>
        @else
            <div class="hidden grid-cols-[minmax(0,2fr)_minmax(0,2fr)_minmax(0,1.4fr)_auto] gap-4 border-b border-line bg-oat px-5 py-2.5 text-xs font-bold text-muted md:grid">
                <span>Produk</span>
                <span>Ukuran &amp; harga</span>
                <span>Resep</span>
                <span class="w-56 text-right">Aksi</span>
            </div>
            <ul class="divide-y divide-line">
                @foreach ($products as $product)
                    @php $info = $productInfo[$product->id]; @endphp
                    <li class="grid gap-3 px-5 py-4 md:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_minmax(0,1.4fr)_auto] md:items-center md:gap-4">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 font-bold {{ $product->is_active ? 'text-charcoal' : 'text-muted' }}">
                                {{ $product->name }}
                                @unless ($product->is_active)
                                    <span class="rounded-full bg-oat-deep px-2 py-0.5 text-xs font-bold text-muted">Nonaktif</span>
                                @endunless
                            </p>
                            @unless ($product->is_active)
                                <p class="mt-0.5 text-xs text-muted">Tidak tampil di menu dan tidak bisa dipesan.</p>
                            @endunless
                            @if ($product->description)
                                <p class="mt-0.5 line-clamp-2 text-sm text-muted">{{ $product->description }}</p>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-1.5">
                            @forelse ($info['sizes'] as $size)
                                <span class="rounded-full border border-line bg-oat px-2.5 py-1 text-xs text-charcoal">
                                    {{ $size }} <span class="font-bold">{{ $rupiah($product->priceFor($size)) }}</span>
                                </span>
                            @empty
                                <span class="rounded-full border border-line bg-oat px-2.5 py-1 text-xs text-charcoal">
                                    Satu ukuran <span class="font-bold">{{ $rupiah($product->base_price) }}</span>
                                </span>
                            @endforelse
                        </div>

                        <div>
                            @if ($info['missing_recipe'] === [])
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-sage-soft px-2.5 py-1 text-xs font-bold text-brand">
                                    <span class="h-1.5 w-1.5 rounded-full bg-brand" aria-hidden="true"></span>
                                    Resep lengkap
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-soft px-2.5 py-1 text-xs font-bold text-amber-text">
                                    <span class="h-1.5 w-1.5 rounded-full bg-cta" aria-hidden="true"></span>
                                    Belum ada resep: {{ collect($info['missing_recipe'])->map(fn ($s) => $s === '-' ? 'satu ukuran' : $s)->join(', ') }}
                                </span>
                                <p class="mt-1 text-xs text-muted">Ukuran ini tampil "Habis" di menu sampai resepnya diisi.</p>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2 md:w-56 md:justify-end">
                            <a
                                href="{{ route('admin.products.edit', $product) }}"
                                class="rounded-brand border border-charcoal/20 px-3 py-1.5 text-sm font-bold text-charcoal transition hover:border-charcoal focus:outline-none focus-visible:ring-2 focus-visible:ring-cta"
                            >
                                Ubah
                            </a>
                            <form action="{{ route('admin.products.active', $product) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $product->is_active ? '0' : '1' }}" />
                                <button
                                    type="submit"
                                    class="rounded-brand px-3 py-1.5 text-sm font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-cta {{ $product->is_active ? 'text-muted hover:bg-oat-deep' : 'bg-sage-soft text-brand hover:bg-opacity-80' }}"
                                >
                                    {{ $product->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                            @if ($info['ordered'])
                                <span class="text-xs leading-4 text-muted" title="Produk yang sudah pernah dipesan tidak bisa dihapus supaya riwayat pesanan tetap utuh.">Sudah pernah dipesan</span>
                            @else
                                <form
                                    action="{{ route('admin.products.destroy', $product) }}"
                                    method="POST"
                                    onsubmit="return confirm({{ Illuminate\Support\Js::from('Hapus produk '.$product->name.' beserta resepnya?') }});"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="rounded-brand px-3 py-1.5 text-sm font-bold text-red-600 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400"
                                    >
                                        Hapus
                                    </button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
