<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', config('app.name', 'YokanCha'))</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-oat text-charcoal font-sans antialiased">
        <div id="app">
            @unless(request()->routeIs('admin.*'))
                <header class="border-b border-brand/10 bg-white">
                    <div class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-4 px-6 py-4">
                        <a href="{{ route('menu.index') }}" class="text-lg font-bold text-charcoal">YokanCha</a>

                        <nav class="flex flex-wrap items-center gap-5">
                            <a href="{{ route('menu.index') }}" class="text-sm font-semibold text-charcoal/70 hover:text-brand">Menu</a>
                            <a href="{{ route('cart.show') }}" class="text-sm font-semibold text-charcoal/70 hover:text-brand">Keranjang</a>
                            <a href="{{ route('orders.lookup') }}" class="text-sm font-semibold text-charcoal/70 hover:text-brand">Cek Pesanan</a>
                        </nav>
                    </div>
                </header>
            @endunless

            @yield('content')
        </div>
    </body>
</html>
