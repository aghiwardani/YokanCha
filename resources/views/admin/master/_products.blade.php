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
                <span class="w-[17rem] text-right">Aksi</span>
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

                        {{-- Action group: same height for every button, one row on desktop. --}}
                        <div class="flex items-center gap-2 md:w-[17rem] md:justify-end">
                            <a
                                href="{{ route('admin.products.edit', $product) }}"
                                class="inline-flex h-9 items-center gap-1.5 rounded-chip bg-brand px-3.5 text-sm font-bold text-surface shadow-sm transition hover:bg-brand/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.6 2.9a2 2 0 0 1 2.8 0l.7.7a2 2 0 0 1 0 2.8l-8.6 8.6a1 1 0 0 1-.46.26l-3.5.9a.75.75 0 0 1-.9-.91l.9-3.5a1 1 0 0 1 .26-.45L13.6 2.9Z"/></svg>
                                Ubah
                            </a>

                            <form action="{{ route('admin.products.active', $product) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $product->is_active ? '0' : '1' }}" />
                                @if ($product->is_active)
                                    <button
                                        type="submit"
                                        class="inline-flex h-9 items-center gap-1.5 rounded-chip border border-line bg-surface px-3.5 text-sm font-bold text-muted transition hover:border-charcoal/40 hover:text-charcoal focus:outline-none focus-visible:ring-2 focus-visible:ring-cta focus-visible:ring-offset-2"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16ZM7.75 6.5a.75.75 0 0 0-.75.75v5.5a.75.75 0 0 0 1.5 0v-5.5a.75.75 0 0 0-.75-.75Zm4.5 0a.75.75 0 0 0-.75.75v5.5a.75.75 0 0 0 1.5 0v-5.5a.75.75 0 0 0-.75-.75Z" clip-rule="evenodd"/></svg>
                                        Nonaktifkan
                                    </button>
                                @else
                                    <button
                                        type="submit"
                                        class="inline-flex h-9 items-center gap-1.5 rounded-chip border border-brand/30 bg-sage-soft px-3.5 text-sm font-bold text-brand transition hover:border-brand focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm-1.6 5.1A.75.75 0 0 0 7.25 7.75v4.5a.75.75 0 0 0 1.15.63l3.5-2.25a.75.75 0 0 0 0-1.26L8.4 7.1Z" clip-rule="evenodd"/></svg>
                                        Aktifkan
                                    </button>
                                @endif
                            </form>

                            @if ($info['ordered'])
                                <span
                                    class="inline-flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-chip border border-line bg-oat text-muted/60"
                                    title="Sudah pernah dipesan, jadi tidak bisa dihapus supaya riwayat pesanan tetap utuh. Nonaktifkan saja kalau tidak dijual lagi."
                                    aria-label="Tidak bisa dihapus: sudah pernah dipesan"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 2a4 4 0 0 0-4 4v2H5.5A1.5 1.5 0 0 0 4 9.5v7A1.5 1.5 0 0 0 5.5 18h9a1.5 1.5 0 0 0 1.5-1.5v-7A1.5 1.5 0 0 0 14.5 8H14V6a4 4 0 0 0-4-4Zm2.5 6V6a2.5 2.5 0 0 0-5 0v2h5Z" clip-rule="evenodd"/></svg>
                                </span>
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
                                        title="Hapus produk"
                                        aria-label="Hapus {{ $product->name }}"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-chip border border-red-200 text-red-600 transition hover:border-red-500 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:ring-offset-2"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75V4H3.75a.75.75 0 0 0 0 1.5h.3l.8 10.4A2.25 2.25 0 0 0 7.1 18h5.8a2.25 2.25 0 0 0 2.24-2.1l.8-10.4h.31a.75.75 0 0 0 0-1.5H14v-.25A2.75 2.75 0 0 0 11.25 1h-2.5ZM7.5 3.75c0-.69.56-1.25 1.25-1.25h2.5c.69 0 1.25.56 1.25 1.25V4h-5v-.25ZM8.5 8a.75.75 0 0 1 .75.75v5.5a.75.75 0 0 1-1.5 0v-5.5A.75.75 0 0 1 8.5 8Zm3.75.75a.75.75 0 0 0-1.5 0v5.5a.75.75 0 0 0 1.5 0v-5.5Z" clip-rule="evenodd"/></svg>
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
