<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Services\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        // A cancelled order's quota has already been released. Transitioning
        // it to any other status would make it "live" again without
        // re-reserving quota, so cancelled orders can never be revived here.
        if ($order->order_status === 'cancelled') {
            throw ValidationException::withMessages([
                'order_status' => 'Pesanan yang sudah dibatalkan tidak dapat diubah statusnya.',
            ]);
        }

        $order->update(['order_status' => $validated['order_status']]);

        return redirect()->route('admin.orders.show', $order)
            ->with('status', 'Status pesanan diubah.');
    }

    public function cancel(Order $order): RedirectResponse
    {
        $wasCancelled = DB::transaction(function () use ($order) {
            // Re-fetch and lock the order row so two near-simultaneous cancel
            // requests can't both pass the guard and both release quota.
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (in_array($locked->order_status, ['cancelled', 'selesai'], true)) {
                return false;
            }

            $locked->load('items');

            foreach ($locked->items as $item) {
                $ppp = PoPeriodProduct::where('po_period_id', $locked->po_period_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if ($ppp) {
                    $this->quotaService->release($ppp, $item->qty);
                }
            }

            $locked->update(['order_status' => 'cancelled']);

            return true;
        });

        $message = $wasCancelled
            ? 'Pesanan dibatalkan dan kuota dikembalikan.'
            : 'Pesanan tidak dapat dibatalkan (sudah dibatalkan atau selesai).';

        return redirect()->route('admin.orders.show', $order)
            ->with('status', $message);
    }
}
