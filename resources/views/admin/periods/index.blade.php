@extends('layouts.admin')

@section('title', 'Periode PO - YokanCha')

@section('admin-content')
<div class="min-h-screen bg-oat py-12 px-4">
    <div class="max-w-3xl mx-auto space-y-8">
        <div class="flex items-center justify-between">
            <h1 class="text-3xl font-bold text-charcoal">Periode PO</h1>
            <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-sm font-semibold text-charcoal underline">
                    Keluar
                </button>
            </form>
        </div>

        @if (session('status'))
            <div class="p-4 bg-brand bg-opacity-10 border-2 border-brand rounded-brand text-charcoal text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-surface rounded-brand shadow-sm p-8">
            <h2 class="text-xl font-bold text-charcoal mb-6">Buat Periode Baru</h2>

            @if ($errors->any())
                <div class="mb-6 p-4 bg-red-50 border-2 border-red-500 rounded-brand">
                    <ul class="text-red-600 text-sm space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('admin.periods.store') }}" method="POST" class="space-y-6">
                @csrf

                <div>
                    <label for="name" class="block text-sm font-semibold text-charcoal mb-2">
                        Nama Periode
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name') }}"
                        class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('name') border-red-500 @enderror"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="opens_at" class="block text-sm font-semibold text-charcoal mb-2">
                            Tanggal Mulai
                        </label>
                        <input
                            type="datetime-local"
                            id="opens_at"
                            name="opens_at"
                            value="{{ old('opens_at') }}"
                            class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('opens_at') border-red-500 @enderror"
                        />
                    </div>

                    <div>
                        <label for="closes_at" class="block text-sm font-semibold text-charcoal mb-2">
                            Tanggal Selesai
                        </label>
                        <input
                            type="datetime-local"
                            id="closes_at"
                            name="closes_at"
                            value="{{ old('closes_at') }}"
                            class="w-full px-4 py-3 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta @error('closes_at') border-red-500 @enderror"
                        />
                    </div>
                </div>

                <button
                    type="submit"
                    class="w-full bg-cta hover:bg-opacity-90 text-surface font-semibold py-3 px-6 rounded-brand transition duration-200"
                >
                    Buat Periode
                </button>
            </form>
        </div>

        <div class="bg-surface rounded-brand shadow-sm p-8">
            <h2 class="text-xl font-bold text-charcoal mb-6">Daftar Periode</h2>

            @if ($periods->isEmpty())
                <p class="text-charcoal opacity-75">Belum ada periode PO.</p>
            @else
                <div class="space-y-4">
                    @foreach ($periods as $period)
                        <div class="flex items-center justify-between border-2 border-brand rounded-brand p-4">
                            <div>
                                <p class="font-semibold text-charcoal">{{ $period->name }}</p>
                                <p class="text-sm text-charcoal opacity-75">
                                    {{ optional($period->opens_at)->format('d M Y H:i') ?? '-' }}
                                    &mdash;
                                    {{ optional($period->closes_at)->format('d M Y H:i') ?? '-' }}
                                </p>
                                <span class="inline-block mt-2 px-3 py-1 text-xs font-semibold rounded-brand bg-oat text-charcoal">
                                    {{ ucfirst($period->status) }}
                                </span>
                            </div>

                            <div class="flex gap-2">
                                @if ($period->status !== 'open')
                                    <form action="{{ route('admin.periods.open', $period) }}" method="POST">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="px-4 py-2 bg-brand text-surface font-semibold rounded-brand hover:bg-opacity-90 transition duration-200"
                                        >
                                            Buka
                                        </button>
                                    </form>
                                @endif

                                @if ($period->status !== 'closed')
                                    <form action="{{ route('admin.periods.close', $period) }}" method="POST">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="px-4 py-2 bg-charcoal text-surface font-semibold rounded-brand hover:bg-opacity-90 transition duration-200"
                                        >
                                            Tutup
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
