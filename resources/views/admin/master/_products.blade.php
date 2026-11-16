{{-- Produk: list with edit/delete; adding and editing use the product form page (it holds the recipe grid). --}}
<section id="produk" class="space-y-8 scroll-mt-8">
    <div class="flex items-center justify-between gap-4">
        <h2 class="text-2xl font-bold text-charcoal">Produk</h2>
        <a href="{{ route('admin.products.create') }}" class="bg-cta hover:bg-opacity-90 text-surface font-semibold py-2 px-4 rounded-brand transition duration-200">
            Tambah Produk
        </a>
    </div>

    <div class="bg-surface rounded-brand shadow-sm p-8">
        <h3 class="text-xl font-bold text-charcoal mb-6">Daftar Produk</h3>

        @if ($products->isEmpty())
            <p class="text-charcoal opacity-75">Belum ada produk.</p>
        @else
            <div class="space-y-4">
                @foreach ($products as $product)
                    <div class="flex flex-wrap items-start justify-between gap-4 border-2 border-brand rounded-brand p-4">
                        <div class="flex-1 min-w-[12rem]">
                            <p class="font-semibold text-charcoal">{{ $product->name }}</p>
                            @if ($product->description)
                                <p class="text-sm text-charcoal opacity-75">{{ $product->description }}</p>
                            @endif
                            <p class="text-sm text-charcoal mt-1">
                                Harga Dasar: Rp {{ number_format($product->base_price, 0, ',', '.') }}
                            </p>
                            <p class="text-xs text-charcoal opacity-75 mt-1">
                                Ukuran: {{ implode(', ', $product->sizesSmallestFirst()) ?: '-' }}
                                &middot;
                                Tingkat Manis: {{ implode(', ', $product->sweetness_options ?? []) ?: '-' }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.products.edit', $product) }}" class="px-4 py-2 bg-charcoal text-surface font-semibold rounded-brand hover:bg-opacity-90 transition duration-200 whitespace-nowrap">
                                Ubah
                            </a>
                            <form
                                action="{{ route('admin.products.destroy', $product) }}"
                                method="POST"
                                onsubmit="return confirm({{ Illuminate\Support\Js::from('Hapus produk '.$product->name.' beserta resepnya?') }});"
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
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
