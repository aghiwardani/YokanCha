@extends('layouts.admin')

@section('title', 'Bahan - YokanCha')

@section('admin-content')
<div class="min-h-screen bg-oat py-12 px-4">
    <div class="max-w-3xl mx-auto space-y-8">
        <h1 class="text-3xl font-bold text-charcoal">Bahan</h1>

        @if (session('status'))
            <div class="p-4 bg-brand bg-opacity-10 border-2 border-brand rounded-brand text-charcoal text-sm">
                {{ session('status') }}
            </div>
        @endif

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
            <h2 class="text-xl font-bold text-charcoal mb-6">Tambah Bahan</h2>

            <form action="{{ route('admin.ingredients.store') }}" method="POST" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label for="new_name" class="block text-sm font-semibold text-charcoal mb-2">Nama Bahan</label>
                        <input
                            type="text"
                            id="new_name"
                            name="name"
                            value="{{ old('name') }}"
                            class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                        />
                    </div>
                    <div>
                        <label for="new_unit" class="block text-sm font-semibold text-charcoal mb-2">Satuan</label>
                        <select
                            id="new_unit"
                            name="unit"
                            class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                        >
                            <option value="g" @selected(old('unit') === 'g')>g</option>
                            <option value="ml" @selected(old('unit') === 'ml')>ml</option>
                        </select>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-charcoal">
                    <input type="checkbox" name="is_sugar" value="1" @checked(old('is_sugar')) />
                    Ini adalah gula
                </label>

                <button
                    type="submit"
                    class="w-full bg-cta hover:bg-opacity-90 text-surface font-semibold py-3 px-6 rounded-brand transition duration-200"
                >
                    Tambah Bahan
                </button>
            </form>
        </div>

        <div class="bg-surface rounded-brand shadow-sm p-8">
            <h2 class="text-xl font-bold text-charcoal mb-6">Daftar Bahan</h2>

            @if ($ingredients->isEmpty())
                <p class="text-charcoal opacity-75">Belum ada bahan.</p>
            @else
                <div class="space-y-4">
                    @foreach ($ingredients as $ingredient)
                        <form
                            action="{{ route('admin.ingredients.update', $ingredient) }}"
                            method="POST"
                            class="flex flex-wrap items-center gap-3 border-2 border-brand rounded-brand p-4"
                        >
                            @csrf
                            @method('PUT')

                            <input
                                type="text"
                                name="name"
                                value="{{ $ingredient->name }}"
                                aria-label="Nama bahan"
                                class="flex-1 min-w-[10rem] px-3 py-2 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                            />
                            <select
                                name="unit"
                                aria-label="Satuan"
                                class="px-3 py-2 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                            >
                                <option value="g" @selected($ingredient->unit === 'g')>g</option>
                                <option value="ml" @selected($ingredient->unit === 'ml')>ml</option>
                            </select>
                            <label class="flex items-center gap-2 text-sm text-charcoal">
                                <input type="checkbox" name="is_sugar" value="1" @checked($ingredient->is_sugar) />
                                Gula
                            </label>
                            <button
                                type="submit"
                                class="px-4 py-2 bg-brand text-surface font-semibold rounded-brand hover:bg-opacity-90 transition duration-200"
                            >
                                Simpan
                            </button>
                        </form>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
