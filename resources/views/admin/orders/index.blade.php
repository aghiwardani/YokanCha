@extends('layouts.app')

@section('title', 'Pesanan - YokanCha')

@section('content')
<div class="min-h-screen bg-oat py-12 px-4">
    <div class="max-w-5xl mx-auto space-y-8">
        <div class="flex items-center justify-between">
            <h1 class="text-3xl font-bold text-charcoal">Pesanan</h1>
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
            <form method="GET" action="{{ route('admin.orders') }}" class="mb-6 flex items-end gap-4">
                <div>
                    <label for="po_period_id" class="block text-sm font-semibold text-charcoal mb-2">
                        Filter Periode
                    </label>
                    <select
                        id="po_period_id"
                        name="po_period_id"
                        class="px-4 py-2 border-2 border-brand rounded-brand focus:outline-none focus:ring-2 focus:ring-cta"
                        onchange="this.form.submit()"
                    >
                        <option value="">Semua Periode</option>
                        @foreach ($periods as $period)
                            <option value="{{ $period->id }}" @selected((string) $selectedPeriodId === (string) $period->id)>
                                {{ $period->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>

            @if ($orders->isEmpty())
                <p class="text-charcoal opacity-75">Belum ada pesanan.</p>
            @else
                <div class="space-y-4">
                    @foreach ($orders as $order)
                        <a
                            href="{{ route('admin.orders.show', $order) }}"
                            class="block border-2 border-brand rounded-brand p-4 hover:bg-oat transition duration-200"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-charcoal">{{ $order->order_number }}</p>
                                    <p class="text-sm text-charcoal opacity-75">
                                        {{ $order->customer_name }} &mdash; {{ $order->customer_whatsapp }}
                                    </p>
                                    <p class="text-xs text-charcoal opacity-60">
                                        {{ optional($order->poPeriod)->name }}
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
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
