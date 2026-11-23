@extends('layouts.app')

@php
    $adminLinks = [
        ['label' => 'Periode PO', 'route' => 'admin.periods', 'active' => ['admin.periods*']],
        ['label' => 'Master Data', 'route' => 'admin.master', 'active' => ['admin.master', 'admin.products*']],
        ['label' => 'Pesanan', 'route' => 'admin.orders', 'active' => ['admin.orders*']],
        ['label' => 'Laporan', 'route' => 'admin.reports', 'active' => ['admin.reports*']],
        ['label' => 'Pengaturan', 'route' => 'admin.settings', 'active' => ['admin.settings*']],
    ];
@endphp

@section('content')
    {{-- Phone: name + Logout on top, the menu as a swipeable row below.
         Wider screens: everything on one line. --}}
    <nav class="border-b border-brand/10 bg-white print:hidden">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3 sm:px-6 md:py-4">
            <span class="order-1 text-lg font-bold text-charcoal">YokanCha Admin</span>

            <form method="POST" action="{{ route('admin.logout') }}" class="order-2 ml-auto md:order-3">
                @csrf
                <button type="submit" class="rounded-full px-3 py-1.5 text-sm font-semibold text-charcoal/70 hover:text-cta">
                    Logout
                </button>
            </form>

            <div class="order-3 -mx-1 flex w-full gap-0.5 overflow-x-auto [scrollbar-width:none] md:order-2 md:mx-0 md:w-auto md:gap-1 [&::-webkit-scrollbar]:hidden">
                @foreach ($adminLinks as $link)
                    @php $active = request()->routeIs(...$link['active']); @endphp
                    <a
                        href="{{ route($link['route']) }}"
                        @if ($active) aria-current="page" @endif
                        class="shrink-0 whitespace-nowrap rounded-full px-2.5 py-1.5 text-sm font-semibold transition md:px-3 {{ $active ? 'bg-sage-soft text-brand' : 'text-charcoal/70 hover:text-brand' }}"
                    >
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </nav>

    @yield('admin-content')
@endsection
