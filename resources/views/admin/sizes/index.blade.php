@extends('layouts.admin')

@section('title', 'Ukuran - YokanCha')

@section('admin-content')
@php
    // Old input belongs to the add form only when that form was the one submitted.
    $addOld = fn (string $key) => old('_form') === 'new' ? old($key) : null;
@endphp
<div class="min-h-screen bg-oat py-12 px-4">
    <div class="max-w-3xl mx-auto space-y-8">
        <h1 class="text-3xl font-bold text-charcoal">Ukuran</h1>

        @if (session('status'))
            <div class="p-4 bg-brand bg-opacity-10 border-2 border-brand rounded-brand text-charcoal text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-surface rounded-brand shadow-sm p-8">
            <h2 class="text-xl font-bold text-charcoal mb-6">Tambah Ukuran</h2>

            <form action="{{ route('admin.sizes.store') }}" method="POST" class="space-y-6">
                @csrf
                <input type="hidden" name="_form" value="new" />

                @if (old('_form') === 'new' && $errors->any())
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
                        <label for="new_name" class="block text-sm font-semibold text-charcoal mb-2">Nama Ukuran</label>
                        <input
                            type="text"
                            id="new_name"
                            name="name"
                            placeholder="Contoh: 500 ml"
                            value="{{ $addOld('name') }}"
                            class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                        />
                    </div>
                    <div>
                        <label for="new_volume" class="block text-sm font-semibold text-charcoal mb-2">Isi (ml)</label>
                        <input
                            type="number"
                            id="new_volume"
                            name="volume_ml"
                            min="1"
                            placeholder="500"
                            value="{{ $addOld('volume_ml') }}"
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
            <h2 class="text-xl font-bold text-charcoal mb-6">Daftar Ukuran</h2>

            @if ($sizes->isEmpty())
                <p class="text-charcoal opacity-75">Belum ada ukuran.</p>
            @else
                <div class="space-y-4">
                    @foreach ($sizes as $size)
                        @php $failed = old('_form') === 'row-'.$size->id; @endphp
                        <form
                            action="{{ route('admin.sizes.update', $size) }}"
                            method="POST"
                            class="flex flex-wrap items-center gap-3 border-2 border-brand rounded-brand p-4"
                        >
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_form" value="row-{{ $size->id }}" />

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
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
