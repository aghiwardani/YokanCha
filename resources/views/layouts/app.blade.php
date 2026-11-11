<!DOCTYPE html>
<html lang="id" @class(['yc-scaled' => ! request()->routeIs('admin.*')])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', config('app.name', 'YokanCha'))</title>
        <meta name="description" content="YokanCha 余暇茶 — matcha botolan per batch. Slow Down, Sip Quietly.">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Gloock&family=Plus+Jakarta+Sans:wght@400;700;800&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="overflow-x-hidden bg-oat font-sans text-charcoal antialiased">
        <div id="app">
            @unless(request()->routeIs('admin.*'))
                @php($home = route('menu.index'))

                {{-- Figma: Header (desktop 6:3, mobile 9:56) --}}
                <header class="bg-brand">
                    <div class="yc-container flex h-[76px] items-center justify-between gap-4 lg:h-16">
                        <a href="{{ $home }}" class="flex shrink-0 items-center" aria-label="YokanCha — beranda">
                            {{-- Light logo (cream badge + cream wordmark), background removed for the sage header. --}}
                            <img src="{{ asset('images/brand/logo-header.png') }}" alt="YokanCha" width="470" height="120" class="h-12 w-auto">
                        </a>

                        <div class="flex items-center gap-7">
                            <nav class="hidden items-center gap-[26px] text-sm font-bold leading-[18px] text-oat lg:flex" aria-label="Navigasi utama">
                                <a href="{{ $home }}#menu" class="hover:underline">Menu</a>
                                <a href="{{ $home }}#cara-pesan" class="hover:underline">Cara pesan</a>
                                <a href="{{ $home }}#penyimpanan" class="hover:underline">Penyimpanan</a>
                                <a href="{{ $home }}#faq" class="hover:underline">Tanya jawab</a>
                            </nav>

                            <a href="{{ route('cart.show') }}" class="yc-btn-cta" data-cart-link>
                                Keranjang{{ $cartCount > 0 ? ' · '.$cartCount : '' }}
                            </a>
                        </div>
                    </div>
                </header>
            @endunless

            @yield('content')

            @unless(request()->routeIs('admin.*'))
                {{-- Add-to-cart confirmation; filled by resources/js/app.js (or the session flash without JS). --}}
                <div class="pointer-events-none fixed inset-x-0 bottom-5 z-50 flex justify-center px-5" role="status" aria-live="polite">
                    <div
                        data-toast
                        data-state="{{ session('cart_status') ? 'ok' : 'idle' }}"
                        class="yc-toast pointer-events-auto flex max-w-[560px] items-center gap-4 rounded-brand border border-charcoal px-5 py-3 text-sm font-bold leading-[22px] shadow-[0_8px_24px_rgba(34,37,33,0.18)]"
                    >
                        <span data-toast-message>{{ session('cart_status') }}</span>
                        <a href="{{ route('cart.show') }}" data-toast-link class="shrink-0 whitespace-nowrap text-cta underline hover:no-underline">Lihat keranjang</a>
                    </div>
                </div>

                {{-- Figma: Footer (desktop 8:118, mobile 9:360) --}}
                <footer class="relative h-[220px] overflow-hidden bg-charcoal text-oat lg:h-[340px]">
                    <div class="yc-container relative z-10 flex flex-col gap-3 pt-6 lg:flex-row lg:items-start lg:justify-between lg:pt-10">
                        <p class="text-base leading-[26px]">YokanCha 余暇茶. Your bottled pocket break<span class="hidden lg:inline">, Batch Release 2026</span>.</p>
                        <p class="yc-mono flex flex-wrap gap-x-2">
                            <span class="hidden lg:inline">Pre-order · Kocok dulu · Simpan dingin ·</span>
                            <a href="{{ route('orders.lookup') }}" class="underline hover:no-underline">Cek pesanan</a>
                        </p>
                    </div>
                    <p class="pointer-events-none absolute left-5 top-[60px] select-none whitespace-nowrap font-display text-[84px] uppercase leading-[22px] tracking-[3px] opacity-[0.14] lg:left-[calc(50%-600px)] lg:top-[120px] lg:text-[190px] lg:tracking-[7.6px]" aria-hidden="true">
                        YokanCha
                    </p>
                </footer>
            @endunless
        </div>
    </body>
</html>
