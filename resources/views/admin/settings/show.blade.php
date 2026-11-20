@extends('layouts.admin')

@section('title', 'Pengaturan - YokanCha')

@php
    $field = 'w-full px-3 py-2 bg-surface border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta';
    $shopErrors = $errors->getBag('shop');
    $accountErrors = $errors->getBag('account');
    $shopValue = fn ($key) => $shopErrors->any() ? old($key) : $shop[$key];
    $label = 'block text-sm font-semibold text-charcoal mb-1';
    $hint = 'mt-1 text-xs text-muted';
@endphp

@section('admin-content')
<div class="min-h-screen bg-oat py-10 px-4">
    <div class="max-w-3xl mx-auto space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-charcoal">Pengaturan</h1>
            <p class="mt-1 text-sm text-muted">Info toko yang dilihat pembeli, dan akun admin kamu.</p>
        </div>

        @if (session('status'))
            <div class="p-4 rounded-brand border border-brand/30 bg-sage-soft text-sm text-charcoal" role="status">
                {{ session('status') }}
            </div>
        @endif

        {{-- Info toko --}}
        <form action="{{ route('admin.settings.shop') }}" method="POST" class="bg-surface rounded-brand border border-line p-6 lg:p-8 space-y-5">
            @csrf
            @method('PUT')

            <div>
                <h2 class="text-xl font-bold text-charcoal">Info toko</h2>
                <p class="mt-1 text-sm text-muted">Tampil di halaman checkout dan konfirmasi pesanan.</p>
            </div>

            @if ($shopErrors->any())
                <ul class="p-4 rounded-brand bg-red-50 text-sm text-red-700 space-y-1">
                    @foreach ($shopErrors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <div>
                <label for="whatsapp" class="{{ $label }}">Nomor WhatsApp</label>
                <input id="whatsapp" name="whatsapp" type="tel" inputmode="tel" value="{{ $shopValue('whatsapp') }}" placeholder="081234567890" class="{{ $field }}">
                <p class="{{ $hint }}">Untuk tombol "Kirim lewat WhatsApp" setelah checkout. Boleh ditulis 08… atau 62….</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="bank_name" class="{{ $label }}">Nama bank / e-wallet</label>
                    <input id="bank_name" name="bank_name" type="text" value="{{ $shopValue('bank_name') }}" placeholder="BCA" class="{{ $field }}">
                </div>
                <div>
                    <label for="bank_account" class="{{ $label }}">Nomor rekening</label>
                    <input id="bank_account" name="bank_account" type="text" inputmode="numeric" value="{{ $shopValue('bank_account') }}" placeholder="1234567890" class="{{ $field }}">
                </div>
            </div>

            <div>
                <label for="bank_holder" class="{{ $label }}">Nama pemilik rekening</label>
                <input id="bank_holder" name="bank_holder" type="text" value="{{ $shopValue('bank_holder') }}" placeholder="Nama sesuai buku tabungan" class="{{ $field }}">
            </div>

            <div class="sm:w-1/2">
                <label for="max_per_variant" class="{{ $label }}">Batas botol per menu dalam satu pesanan</label>
                <input id="max_per_variant" name="max_per_variant" type="number" min="1" max="999" value="{{ $shopValue('max_per_variant') }}" class="{{ $field }}">
                <p class="{{ $hint }}">Supaya satu orang tidak memborong satu menu.</p>
            </div>

            <div class="rounded-brand bg-oat p-4 text-sm text-charcoal">
                <p class="text-xs font-semibold text-muted mb-1">Yang dilihat pembeli saat transfer</p>
                <p>{{ $shop['bank_name'] }} {{ $shop['bank_account'] }} a.n. {{ $shop['bank_holder'] }}</p>
            </div>

            <button type="submit" class="px-5 py-2.5 bg-cta text-surface font-semibold rounded-brand hover:bg-opacity-90">
                Simpan info toko
            </button>
        </form>

        {{-- Akun admin --}}
        <form action="{{ route('admin.settings.account') }}" method="POST" class="bg-surface rounded-brand border border-line p-6 lg:p-8 space-y-5">
            @csrf
            @method('PUT')

            <div>
                <h2 class="text-xl font-bold text-charcoal">Akun admin</h2>
                <p class="mt-1 text-sm text-muted">Email dan password ini dipakai untuk login ke halaman admin.</p>
            </div>

            @if ($accountErrors->any())
                <ul class="p-4 rounded-brand bg-red-50 text-sm text-red-700 space-y-1">
                    @foreach ($accountErrors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="{{ $label }}">Nama</label>
                    <input id="name" name="name" type="text" autocomplete="name" value="{{ $accountErrors->any() ? old('name') : $user->name }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="email" class="{{ $label }}">Email login</label>
                    <input id="email" name="email" type="email" autocomplete="username" value="{{ $accountErrors->any() ? old('email') : $user->email }}" class="{{ $field }}">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="password" class="{{ $label }}">Password baru</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" class="{{ $field }}">
                    <p class="{{ $hint }}">Minimal 8 karakter. Kosongkan kalau tidak mau ganti.</p>
                </div>
                <div>
                    <label for="password_confirmation" class="{{ $label }}">Ulangi password baru</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="{{ $field }}">
                </div>
            </div>

            <div class="border-t border-line pt-5 sm:w-1/2">
                <label for="current_password" class="{{ $label }}">Password sekarang</label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password" class="{{ $field }}">
                <p class="{{ $hint }}">Wajib diisi untuk menyimpan perubahan akun.</p>
            </div>

            <button type="submit" class="px-5 py-2.5 bg-brand text-surface font-semibold rounded-brand hover:bg-opacity-90">
                Simpan akun
            </button>
        </form>
    </div>
</div>
@endsection
