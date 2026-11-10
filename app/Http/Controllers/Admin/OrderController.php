<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Services\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private QuotaService $quotaService)
    {
    }

    public function index(Request $request): View
    {
        $periods = PoPeriod::orderByDesc('created_at')->get();

        $query = Order::with('poPeriod', 'items')->orderByDesc('created_at');

        if ($request->filled('po_period_id')) {
            $query->where('po_period_id', $request->input('po_period_id'));
        }

        $orders = $query->get();

        return view('admin.orders.index', [
            'orders' => $orders,
            'periods' => $periods,
            'selectedPeriodId' => $request->input('po_period_id'),
        ]);
    }

    public function show(Order $order): View
    {
        $order->load('poPeriod', 'items.product');

        return view('admin.orders.show', [
            'order' => $order,
        ]);
    }

    public function markPaid(Order $order): RedirectResponse
    {
        $order->update(['payment_status' => 'paid']);

        return redirect()->route('admin.orders.show', $order)
            ->with('status', 'Pesanan ditandai sudah bayar.');
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'order_status' => ['required', 'in:new,diproses,siap_diambil,selesai'],
        ]);

        $order->update(['order_status' => $validated['order_status']]);

        return redirect()->route('admin.orders.show', $order)
            ->with('status', 'Status pesanan diubah.');
    }

    public function cancel(Order $order): RedirectResponse
    {
        if ($order->order_status === 'cancelled') {
            return redirect()->route('admin.orders.show', $order)
                ->with('status', 'Pesanan sudah dibatalkan sebelumnya.');
        }

        $order->load('items');

        foreach ($order->items as $item) {
            $ppp = PoPeriodProduct::where('po_period_id', $order->po_period_id)
                ->where('product_id', $item->product_id)
                ->first();

            if ($ppp) {
                $this->quotaService->release($ppp, $item->qty);
            }
        }

        $order->update(['order_status' => 'cancelled']);

        return redirect()->route('admin.orders.show', $order)
            ->with('status', 'Pesanan dibatalkan dan kuota dikembalikan.');
    }
}
