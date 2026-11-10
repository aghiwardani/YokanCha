@extends('layouts.app')

@section('content')
    <nav class="border-b border-brand/10 bg-white">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-6 py-4">
            <div class="flex flex-wrap items-center gap-6">
                <span class="text-lg font-bold text-charcoal">YokanCha Admin</span>

                <a
                    href="{{ route('admin.periods') }}"
                    class="text-sm font-semibold text-charcoal/70 hover:text-brand {{ request()->routeIs('admin.periods*') ? 'text-brand' : '' }}"
                >
                    Periode PO
                </a>

                <a
                    href="{{ route('admin.products') }}"
                    class="text-sm font-semibold text-charcoal/70 hover:text-brand {{ request()->routeIs('admin.products*') ? 'text-brand' : '' }}"
                >
                    Produk
                </a>

                <a
                    href="{{ route('admin.orders') }}"
                    class="text-sm font-semibold text-charcoal/70 hover:text-brand {{ request()->routeIs('admin.orders*') ? 'text-brand' : '' }}"
                >
                    Pesanan
                </a>
            </div>

            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="text-sm font-semibold text-charcoal/70 hover:text-cta">
                    Logout
                </button>
            </form>
        </div>
    </nav>

    @yield('admin-content')
@endsection
