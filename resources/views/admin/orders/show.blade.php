@extends('layouts.admin')

@section('title', 'Detail Pesanan - YokanCha')

@section('admin-content')
<div class="min-h-screen bg-oat py-12 px-4">
    <div class="max-w-3xl mx-auto space-y-8">
        <div class="flex items-center justify-between">
            <h1 class="text-3xl font-bold text-charcoal">Detail Pesanan</h1>
            <a href="{{ route('admin.orders') }}" class="text-sm font-semibold text-charcoal underline">
                Kembali
            </a>
        </div>

        @if (session('status'))
            <div class="p-4 bg-brand bg-opacity-10 border-2 border-brand rounded-brand text-charcoal text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-surface rounded-brand shadow-sm p-8 space-y-6">
            <div>
                <p class="font-semibold text-charcoal text-lg">{{ $order->order_number }}</p>
                <p class="text-sm text-charcoal opacity-75">
                    {{ $order->customer_name }} &mdash; {{ $order->customer_whatsapp }}
                </p>
                <p class="text-xs text-charcoal opacity-60">
                    Periode: {{ optional($order->poPeriod)->name }}
                </p>
            </div>

            <div class="flex gap-2">
                <span class="inline-block px-3 py-1 text-xs font-semibold rounded-brand bg-oat text-charcoal">
                    {{ $order->payment_status === 'paid' ? 'Sudah Bayar' : 'Belum Bayar' }}
                </span>
                <span class="inline-block px-3 py-1 text-xs font-semibold rounded-brand bg-charcoal text-surface">
                    @switch($order->order_status)
                        @case('new') Baru @break
                        @case('diproses') Diproses @break
                        @case('siap_diambil') Siap Diambil @break
                        @case('selesai') Selesai @break
                        @case('cancelled') Dibatalkan @break
                        @default {{ $order->order_status }}
                    @endswitch
                </span>
            </div>

            <div>
                <h2 class="text-lg font-bold text-charcoal mb-3">Item Pesanan</h2>
                <div class="space-y-2">
                    @foreach ($order->items as $item)
                        <div class="flex items-center justify-between border-2 border-brand rounded-brand p-3">
                            <div>
                                <p class="font-semibold text-charcoal">{{ optional($item->product)->name }}</p>
                                <p class="text-xs text-charcoal opacity-60">
                                    {{ $item->size }} / {{ $item->sweetness }} &mdash; Qty: {{ $item->qty }}
                                </p>
                            </div>
                            <p class="font-semibold text-charcoal">
                                Rp{{ number_format($item->unit_price * $item->qty, 0, ',', '.') }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-4 border-t-2 border-brand">
                @if ($order->payment_status !== 'paid')
                    <form action="{{ route('admin.orders.mark-paid', $order) }}" method="POST">
                        @csrf
                        <button
                            type="submit"
                            class="px-4 py-2 bg-cta text-surface font-semibold rounded-brand hover:bg-opacity-90 transition duration-200"
                        >
                            Tandai Sudah Bayar
                        </button>
                    </form>
                @endif

                @if ($order->order_status !== 'cancelled')
                    <form action="{{ route('admin.orders.status', $order) }}" method="POST" class="flex items-center gap-2">
                        @csrf
                        <select
                            name="order_status"
                            class="px-3 py-2 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                        >
                            <option value="new" @selected($order->order_status === 'new')>Baru</option>
                            <option value="diproses" @selected($order->order_status === 'diproses')>Diproses</option>
                            <option value="siap_diambil" @selected($order->order_status === 'siap_diambil')>Siap Diambil</option>
                            <option value="selesai" @selected($order->order_status === 'selesai')>Selesai</option>
                        </select>
                        <button
                            type="submit"
                            class="px-4 py-2 bg-brand text-surface font-semibold rounded-brand hover:bg-opacity-90 transition duration-200"
                        >
                            Ubah Status
                        </button>
                    </form>

                    @if ($order->order_status !== 'selesai')
                        <form action="{{ route('admin.orders.cancel', $order) }}" method="POST">
                            @csrf
                            <button
                                type="submit"
                                class="px-4 py-2 bg-charcoal text-surface font-semibold rounded-brand hover:bg-opacity-90 transition duration-200"
                            >
                                Batalkan Pesanan
                            </button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
