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
                            <div class="flex flex-wrap items-center gap-3 border-2 border-brand rounded-brand p-4">
                                <label for="stock_{{ $ingredient->id }}" class="flex-1 min-w-[8rem] font-semibold text-charcoal">
                                    {{ $ingredient->name }} <span class="font-normal opacity-75">({{ $ingredient->unit }})</span>
                                </label>
                                <input
                                    type="text" inputmode="decimal" data-number
                                    step="0.01"
                                    min="0"
                                    id="stock_{{ $ingredient->id }}"
                                    name="stock[{{ $ingredient->id }}]"
                                    value="{{ $value }}"
                                    class="w-32 px-3 py-2 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('stock.'.$ingredient->id) border-red-500 @enderror"
                                />
                                <span class="text-sm text-charcoal">Terpakai: {{ $fmt($row?->used ?? 0) }} {{ $ingredient->unit }}</span>
                                <span class="text-sm text-charcoal">Sisa: {{ $fmt($row?->remaining() ?? 0) }} {{ $ingredient->unit }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

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
