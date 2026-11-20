@extends('layouts.app')

@section('title', 'Sebentar dulu - YokanCha')

@section('content')
    @php($wait = (int) ($exception->getHeaders()['Retry-After'] ?? 60))
    <section class="yc-container flex min-h-[60vh] flex-col items-center justify-center gap-4 py-16 text-center">
        <x-mascot name="lean" sizes="139px" class="yc-mascot h-[160px] w-auto" />
        <h1 class="font-display text-[28px] leading-8 text-charcoal">Pelan-pelan dulu.</h1>
        <p class="max-w-md text-base leading-[26px] text-muted">
            Terlalu banyak permintaan dalam waktu singkat. Tunggu sekitar {{ $wait }} detik, lalu coba lagi.
        </p>
        <a href="{{ route('menu.index') }}" class="yc-btn-cta">Kembali ke menu</a>
    </section>
@endsection
