@extends('layouts.app')

@section('title', 'YokanCha — Slow Down, Sip Quietly')

@php
    // "Batas pesan 20.00" in the Figma hero; follows the open period's closing time when set.
    $closesAt = $period?->closes_at;
    $deadline = $closesAt
        ? ($closesAt->isSameDay(now()) ? $closesAt->format('H.i') : $closesAt->translatedFormat('j M, H.i'))
        : '20.00';
    // For sentences: "jam 20.00" today, "12 Nov jam 20.00" on another day.
    $deadlinePhrase = $closesAt && ! $closesAt->isSameDay(now())
        ? $closesAt->translatedFormat('j M').' jam '.$closesAt->format('H.i')
        : 'jam '.$deadline;

    // "250ml" and "250 ml" are the same size; list each once.
    $sizeLabels = $items->flatMap(fn ($item) => $item->product->size_options ?? [])
        ->unique(fn ($size) => strtolower(str_replace(' ', '', $size)))
        ->values();
    $menuMeta = $items->count().' varian'.($sizeLabels->isNotEmpty() ? ' · '.$sizeLabels->implode(' & ') : '');

    // Sizes named in sentences: what this batch actually offers, else the sizes normally sold.
    $copySizes = $sizeLabels->isNotEmpty() ? $sizeLabels : collect(config('yokancha.sizes'));
    $sizesAnd = $copySizes->join(', ', $copySizes->count() > 2 ? ', dan ' : ' dan ');   // 250 ml, 500 ml, dan 1 liter
    $sizesOr = $copySizes->join(', ', $copySizes->count() > 2 ? ', atau ' : ' atau ');   // 250 ml, 500 ml, atau 1 liter

    $maxPerVariant = config('yokancha.max_per_variant');

    $makna = [
        ['kanji' => '余暇', 'title' => 'Yoka · Waktu luang', 'body' => 'Waktu buat santai sebentar di sela kerjaan.'],
        ['kanji' => '茶', 'title' => 'Cha · Teh hijau', 'body' => 'Matcha racikan sendiri, campuran ceremonial grade sama premium grade, ditambah susu D.O.A.'],
        ['kanji' => '予感', 'title' => 'Yokan · Firasat baik', 'body' => 'Bunyinya mirip kata "firasat baik" dalam bahasa Jepang. Anggap aja doa kecil buat harimu.'],
    ];

    $langkah = [
        ['no' => '01', 'title' => 'Pilih botol', 'body' => "Pilih rasa, ukuran {$sizesOr}, dan tingkat manis."],
        ['no' => '02', 'title' => 'Masuk keranjang', 'body' => "Atur jumlahnya. Maksimal {$maxPerVariant} botol per rasa."],
        ['no' => '03', 'title' => 'Kirim lewat WhatsApp', 'body' => 'Isi nama dan nomor WA. Pesannya sudah terisi, tinggal kirim.'],
        ['no' => '04', 'title' => 'Bayar dan ambil', 'body' => "Transfer, kirim buktinya, lalu ambil di kantor. PO tutup {$deadlinePhrase}."],
    ];

    $perawatan = [
        ['title' => 'Simpan di kulkas', 'body' => 'Suhu 2–5°C udah pas. Jangan masuk freezer.'],
        ['title' => 'Kocok sebelum diminum', 'body' => 'Matchanya suka ngendap di bawah, jadi kocok dulu sekitar 10 detik.'],
        ['title' => 'Habiskan dalam 3 hari', 'body' => 'Nggak pakai pengawet, jadi jangan disimpan lama-lama.'],
    ];

    $faq = [
        ['q' => 'Matchanya pakai grade apa?', 'a' => 'Aku pakai Ceremonial Blend, campuran matcha ceremonial grade sama premium grade yang aku racik sendiri.'],
        ['q' => 'Tahan berapa lama?', 'a' => 'Kalau disimpan di kulkas, tahan sampai 3 hari. Soalnya nggak pakai pengawet, jadi lebih enak kalau cepat diminum.'],
        ['q' => 'Cara pesannya gimana?', 'a' => 'Tinggal pilih di menu terus masukin ke keranjang. Pas checkout, isi nama sama nomor WA, nanti pesanannya bisa langsung dikirim ke WhatsApp aku.'],
        ['q' => 'Botolnya diambil di mana?', 'a' => 'Di kantor aja. Nanti aku chat kalau pesananmu sudah siap.'],
        ['q' => 'Kenapa stoknya bisa habis?', 'a' => 'Karena aku bikin sendiri, jumlahnya terbatas tiap batch. Kalau kehabisan, bisa ikut batch berikutnya.'],
    ];
@endphp

@section('content')
    <main>
        {{-- ===== Hero (desktop 6:15, mobile 9:60) ===== --}}
        <section class="yc-container flex flex-col gap-5 pb-10 pt-8 lg:flex-row lg:items-center lg:gap-12 lg:pb-16 lg:pt-14">
            <div class="flex min-w-0 flex-1 flex-col items-start gap-5 lg:gap-[26px]">
                <span class="hidden items-center gap-[10px] rounded-chip bg-brand px-[14px] py-2 text-oat lg:inline-flex">
                    <img src="{{ asset('images/landing/hero-dot.svg') }}" alt="" width="8" height="8">
                    <span class="yc-mono">Batch Release 2026 · {{ $period ? 'Pre-order dibuka' : 'Pre-order ditutup' }}</span>
                </span>
                <span class="yc-tag-amber lg:hidden">Batch Release 2026</span>

                <h1 class="font-display text-[52px] leading-none tracking-[-2px] text-charcoal lg:text-[104px] lg:leading-[98px]">
                    <span class="lg:block">Slow</span>
                    <span class="lg:block">Down,</span>
                    <span class="lg:block">Sip</span>
                    <span class="lg:mt-[9px] lg:inline-block lg:-rotate-[2.5deg] lg:rounded-card lg:bg-brand lg:px-5 lg:pb-[10px] lg:pt-1 lg:text-oat">Quietly.</span>
                </h1>

                <p class="max-w-[460px] text-lg leading-[29px] text-muted">
                    Matcha Ceremonial Blend, susu D.O.A, dan sirup maple.<span class="hidden lg:inline"> Ada ukuran {{ $sizesAnd }}, pesan sebelum {{ $deadlinePhrase }}.</span>
                </p>

                <div class="flex flex-wrap gap-[10px] lg:gap-3">
                    <a href="#menu" class="yc-btn-cta">Pesan sekarang</a>
                    <a href="#arti" class="yc-btn-ghost">Tentang YokanCha</a>
                </div>

                <ul class="hidden flex-wrap gap-2 lg:flex">
                    @foreach (['Ceremonial Blend', 'Batas pesan '.$deadline, 'Ambil di kantor', 'Tahan 3 hari di kulkas'] as $fakta)
                        <li class="yc-mono rounded-chip border border-charcoal px-3 py-2 text-charcoal">{{ $fakta }}</li>
                    @endforeach
                </ul>
            </div>

            {{-- Panggung maskot: desktop --}}
            <div class="group relative hidden h-[540px] w-[500px] shrink-0 overflow-hidden rounded-card border border-charcoal bg-brand lg:block" aria-hidden="true">
                <img src="{{ asset('images/landing/hero-leaf-pattern.svg') }}" alt="" class="absolute left-[-2px] top-[8px] h-[523.4px] w-[481.3px] max-w-none">
                <img src="{{ asset('images/landing/hero-floor-shadow.svg') }}" alt="" class="yc-mascot-shadow absolute left-[94px] top-[491px] h-[26px] w-[310px] max-w-none">
                <img src="{{ asset('images/landing/yoka-hero.png') }}" alt="" class="yc-mascot yc-mascot-float absolute left-[79px] top-[37.89px] h-[464px] w-[339px] max-w-none object-cover">

                <div class="absolute left-[13px] top-[13px] h-[104px] w-[104px]">
                    <img src="{{ asset('images/landing/hero-seal.svg') }}" alt="" class="absolute inset-0 h-full w-full">
                    <p class="yc-mono absolute inset-x-0 top-[38px] text-center text-charcoal">YokanCha<br>Batch 2026</p>
                </div>

                <div class="absolute left-[25px] top-[394px] flex h-[44.77px] w-[125.74px] items-center justify-center">
                    <span class="yc-tag-amber -rotate-[7deg]">Stok terbatas</span>
                </div>
                <div class="absolute left-[313.86px] top-[321px] flex h-[50.01px] w-[195.08px] items-center justify-center">
                    <span class="yc-tag rotate-[6deg]">A soft hug in a bottle</span>
                </div>
            </div>

            {{-- Panggung maskot: mobile --}}
            <div class="relative h-[340px] w-full overflow-hidden rounded-card bg-brand lg:hidden" aria-hidden="true">
                <div class="absolute left-1/2 top-10 h-[280px] w-[200px] -translate-x-1/2">
                    <img src="{{ asset('images/landing/yoka-hero.png') }}" alt="" class="yc-mascot yc-mascot-float h-full w-full object-contain">
                </div>
                <span class="yc-tag-amber absolute left-[14px] top-[14px]">A soft hug in a bottle</span>
            </div>
        </section>

        {{-- ===== Pita (desktop 6:108, mobile 9:76) ===== --}}
        <div class="relative hidden h-[120px] overflow-hidden lg:block" aria-hidden="true">
            <div class="absolute left-[-30px] top-[9px] flex h-16 w-[calc(100%+60px)] min-w-[1500px] -rotate-[1.6deg] items-center gap-7 overflow-hidden whitespace-nowrap border border-charcoal bg-brand p-[14px]">
                @foreach (range(1, 3) as $loopIndex)
                    @foreach (['Slow down', 'Sip quietly', 'Ceremonial Blend', 'Batch 2026', 'Pre-order', 'Kocok dulu'] as $kata)
                        <span class="shrink-0 font-display text-[32px] uppercase leading-[30px] tracking-[2px] text-oat">{{ $kata }}</span>
                        <img src="{{ asset('images/landing/leaf-ribbon.svg') }}" alt="" class="h-[22px] w-[22px] shrink-0">
                    @endforeach
                @endforeach
            </div>
        </div>
        <div class="yc-mono flex gap-[14px] overflow-hidden whitespace-nowrap bg-brand py-3 pl-5 text-oat lg:hidden" aria-hidden="true">
            <span>Slow down ◆</span><span>Sip quietly ◆</span><span>Ceremonial Blend ◆</span><span>Batch 2026 ◆</span><span>Pre-order ◆</span><span>Kocok dulu</span>
        </div>

        {{-- ===== Menu (desktop 7:7, mobile 9:82) ===== --}}
        <section id="menu" class="yc-container flex scroll-mt-4 flex-col gap-4 pb-10 pt-10 lg:gap-8 lg:pb-24 lg:pt-12">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex flex-col gap-3">
                    <h2 class="yc-h2">Menu batch ini</h2>
                    <p class="max-w-[560px] text-base leading-[26px] text-muted">
                        Harga per botol. Pilih ukuran dan tingkat manis, lalu masukkan ke keranjang.
                    </p>
                </div>

                @if ($period && $items->isNotEmpty())
                    <p class="yc-mono hidden shrink-0 text-brand lg:block">{{ $menuMeta }}</p>
                @endif
            </div>

            @if ($errors->any())
                <div class="rounded-brand border border-cta bg-amber-soft px-5 py-4 text-sm text-charcoal" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (! $period)
                <div class="flex flex-col items-center gap-4 rounded-card border border-charcoal bg-surface px-8 py-12 text-center">
                    <img src="{{ asset('images/landing/yoka-lean.png') }}" alt="" class="yc-mascot yc-mascot-breathe h-[160px] w-auto">
                    <p class="font-display text-[25px] leading-7 text-charcoal">Pre-order lagi tutup dulu.</p>
                    <p class="text-base leading-[26px] text-muted">Info batch berikutnya nanti ada di sini.</p>
                </div>
            @elseif ($items->isEmpty())
                <div class="rounded-card border border-charcoal bg-surface px-8 py-12 text-center">
                    <p class="font-display text-[25px] leading-7 text-charcoal">Menu batch ini belum siap.</p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($items as $item)
                        @include('menu._card', ['item' => $item])
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ===== Arti YokanCha (desktop 12:73, mobile 12:144) ===== --}}
        <section id="arti" class="yc-container flex scroll-mt-4 flex-col gap-3 pb-10 pt-2 lg:gap-7 lg:pb-16 lg:pt-8">
            <h2 class="yc-h2">Arti 余暇茶</h2>
            <p class="hidden text-base leading-[26px] text-muted lg:block">Nama YokanCha diambil dari tiga kata dalam bahasa Jepang.</p>

            <div class="grid grid-cols-1 gap-3 lg:grid-cols-3 lg:gap-4">
                @foreach ($makna as $m)
                    <div class="flex flex-col gap-2 rounded-card border border-charcoal bg-surface p-5 lg:gap-[10px] lg:p-6">
                        <p class="font-display text-[36px] leading-9 text-brand lg:text-[44px] lg:leading-[30px]">{{ $m['kanji'] }}</p>
                        <h3 class="font-display text-xl leading-5 text-charcoal lg:text-[25px] lg:leading-7">{{ $m['title'] }}</h3>
                        <p class="text-sm leading-[22px] text-muted">{{ $m['body'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="hidden rounded-card border border-charcoal bg-sage-soft px-7 py-6 lg:block">
                <p class="text-lg leading-[29px] text-charcoal">
                    Yoka itu maskot YokanCha, roh busa matcha yang santai. Pesannya sederhana: istirahat sebentar, minum pelan-pelan.
                </p>
            </div>
        </section>

        {{-- ===== Cara pesan (desktop 8:54, mobile 9:299) ===== --}}
        <section id="cara-pesan" class="relative scroll-mt-4 bg-brand py-10 lg:py-24">
            <div class="group absolute right-[max(40px,calc(50%-680px))] top-[-120px] hidden h-[325px] w-[240px] lg:block" aria-hidden="true">
                <img src="{{ asset('images/landing/yoka-chasen.png') }}" alt="" style="--yc-dur: 5.2s; --yc-delay: -2s" class="yc-mascot yc-mascot-breathe absolute left-px top-[3.79px] h-[321px] w-[239px] max-w-none object-cover">
            </div>

            <div class="yc-container flex flex-col gap-[14px] lg:gap-10">
                <h2 class="yc-h2 text-oat">Cara pesan</h2>
                <p class="hidden max-w-[620px] text-lg leading-[29px] text-oat lg:block">
                    Tanpa akun. Pesananmu nanti jadi chat WhatsApp yang tinggal dikirim ke aku.
                </p>

                <ol class="grid grid-cols-1 gap-[14px] lg:grid-cols-4 lg:gap-4">
                    @foreach ($langkah as $l)
                        <li class="flex gap-[14px] rounded-brand border border-charcoal bg-surface p-4 lg:flex-col lg:gap-3 lg:rounded-card lg:p-6">
                            <span class="font-display text-[36px] leading-none text-amber-text lg:text-[52px] lg:leading-[52px]">{{ $l['no'] }}</span>
                            <div class="flex flex-1 flex-col gap-1 lg:gap-3">
                                <h3 class="font-display text-xl leading-none text-charcoal lg:text-[25px] lg:leading-7">{{ $l['title'] }}</h3>
                                <p class="text-sm leading-[22px] text-muted">{{ $l['body'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- ===== Biar tetap enak (desktop 8:75, mobile 9:321) ===== --}}
        <section id="penyimpanan" class="yc-container flex scroll-mt-4 flex-col gap-4 py-10 lg:flex-row lg:items-start lg:gap-12 lg:py-24">
            <div class="flex min-w-0 flex-1 flex-col gap-4 lg:gap-6">
                <h2 class="yc-h2">Biar tetap enak</h2>
                @foreach ($perawatan as $p)
                    <div class="flex flex-col gap-1 border-t border-line pt-4">
                        <h3 class="text-base font-bold leading-6 text-charcoal">{{ $p['title'] }}</h3>
                        <p class="text-base leading-[26px] text-muted">{{ $p['body'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="group relative h-[280px] w-full shrink-0 overflow-hidden rounded-card border border-charcoal bg-sage-soft lg:h-[460px] lg:w-[480px]" aria-hidden="true">
                <img src="{{ asset('images/landing/yoka-thumbs.png') }}" alt="" style="--yc-delay: -1.4s" class="yc-mascot yc-mascot-sway absolute left-1/2 top-[43px] h-[217px] w-[177px] max-w-none -translate-x-1/2 object-cover lg:top-[77.79px] lg:h-[361px] lg:w-[295px]">
                <span class="yc-tag-amber absolute left-[14px] top-[14px] lg:left-[19px] lg:top-[19px]">Kocok dulu</span>
            </div>
        </section>

        {{-- ===== FAQ (desktop 8:92, mobile 9:337) ===== --}}
        <section id="faq" class="yc-container flex scroll-mt-4 flex-col gap-4 pb-10 pt-2 lg:flex-row lg:items-start lg:gap-16 lg:pb-24 lg:pt-8">
            <div class="flex shrink-0 flex-col gap-5 lg:w-[360px]">
                <h2 class="font-display text-[36px] leading-9 tracking-[-0.6px] text-charcoal lg:text-[44px] lg:leading-[64px]">Yang sering ditanyain</h2>
                <div class="group relative hidden h-[240px] w-[200px] lg:block" aria-hidden="true">
                    <img src="{{ asset('images/landing/yoka-lean.png') }}" alt="" style="--yc-dur: 4.3s; --yc-delay: -3s" class="yc-mascot yc-mascot-breathe absolute left-0 top-[-0.21px] h-[240px] w-[207px] max-w-none object-cover">
                </div>
            </div>

            <div class="flex min-w-0 flex-1 flex-col gap-[10px] lg:gap-3">
                @foreach ($faq as $f)
                    <details class="group rounded-brand border border-charcoal bg-surface px-[19px] py-[17px] lg:px-6 lg:py-5" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-charcoal [&::-webkit-details-marker]:hidden">
                            <span class="text-base font-bold leading-6">{{ $f['q'] }}</span>
                            <span class="font-display text-[25px] leading-7" aria-hidden="true">
                                <span class="group-open:hidden">+</span><span class="hidden group-open:inline">–</span>
                            </span>
                        </summary>
                        <p class="mt-3 text-base leading-[26px] text-muted">{{ $f['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </section>
    </main>
@endsection
