<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PoPeriod;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    private const STATUSES = ['new', 'diproses', 'siap_diambil', 'selesai', 'cancelled'];

    public function __construct(private StockService $stock)
    {
    }

    public function index(Request $request): View
    {
        $periods = PoPeriod::orderByDesc('created_at')->get();

        $query = Order::with('poPeriod', 'items.product')->orderByDesc('created_at')->orderByDesc('id');

        if ($request->filled('po_period_id')) {
            $query->where('po_period_id', $request->input('po_period_id'));
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_whatsapp', 'like', "%{$search}%")
                    ->orWhere('order_number', 'like', "%{$search}%");
            });
        }

        // Small shop: the period's orders fit in memory, so the summary and
        // the status counts are worked out from one query.
        $all = $query->get();
        $live = $all->where('order_status', '!=', 'cancelled');
        $totalOf = fn ($orders) => $orders->sum(fn (Order $o) => $o->total());

        $summary = [
            'orders' => $live->count(),
            'bottles' => $live->sum(fn (Order $o) => $o->items->sum('qty')),
            'revenue' => $totalOf($live),
            'paid' => $totalOf($live->where('payment_status', 'paid')),
            'unpaid' => $totalOf($live->where('payment_status', '!=', 'paid')),
            'unpaidCount' => $live->where('payment_status', '!=', 'paid')->count(),
        ];

        $statusCounts = $all->countBy('order_status');

        $payment = $request->input('payment');
        $status = $request->input('status');

        $orders = $all
            ->when($payment === 'paid', fn ($c) => $c->where('payment_status', 'paid'))
            ->when($payment === 'unpaid', fn ($c) => $c->where('payment_status', '!=', 'paid'))
            ->when(in_array($status, self::STATUSES, true), fn ($c) => $c->where('order_status', $status))
            ->values();

        return view('admin.orders.index', [
            'orders' => $orders,
            'periods' => $periods,
            'selectedPeriodId' => $request->input('po_period_id'),
            'summary' => $summary,
            'statusCounts' => $statusCounts,
            'payment' => $payment,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function show(Order $order): View
    {
        $order->load('poPeriod', 'items.product');

        return view('admin.orders.show', [
            'order' => $order,
        ]);
    }

    public function markPaid(Request $request, Order $order): RedirectResponse
    {
        $order->update(['payment_status' => 'paid']);

        return $this->backTo($request, $order, "Pesanan {$order->customer_name} ditandai sudah bayar.");
    }

    // Undo for a payment marked by mistake.
    public function markUnpaid(Request $request, Order $order): RedirectResponse
    {
        $order->update(['payment_status' => 'unpaid']);

        return $this->backTo($request, $order, "Pesanan {$order->customer_name} ditandai belum bayar.");
    }

    /**
     * Changes made from the order list go back to the same list (filters
     * kept) and scroll to the order; the rest go to the order's page.
     */
    private function backTo(Request $request, Order $order, string $message): RedirectResponse
    {
        if ($request->input('from') === 'list') {
            $previous = strtok(url()->previous(), '#');
            $list = route('admin.orders');
            $target = str_starts_with($previous, $list) ? $previous : $list;

            return redirect()->to($target.'#order-'.$order->id)->with('status', $message);
        }

        return redirect()->route('admin.orders.show', $order)->with('status', $message);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'order_status' => ['required', 'in:new,diproses,siap_diambil,selesai'],
        ]);

        // A cancelled order's stock has already been returned. Transitioning
        // it to any other status would make it "live" again without
        // re-reserving stock, so cancelled orders can never be revived here.
        if ($order->order_status === 'cancelled') {
            throw ValidationException::withMessages([
                'order_status' => 'Pesanan yang sudah dibatalkan tidak dapat diubah statusnya.',
            ]);
        }

        $order->update(['order_status' => $validated['order_status']]);

        $label = ['new' => 'Baru', 'diproses' => 'Diproses', 'siap_diambil' => 'Siap diambil', 'selesai' => 'Selesai'][$validated['order_status']];

        return $this->backTo($request, $order, "Status pesanan {$order->customer_name} diubah ke {$label}.");
    }

    public function cancel(Order $order): RedirectResponse
    {
        $wasCancelled = DB::transaction(function () use ($order) {
            // Re-fetch and lock the order row so two near-simultaneous cancel
            // requests can't both pass the guard and both return stock.
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (in_array($locked->order_status, ['cancelled', 'selesai'], true)) {
                return 'none';
            }

            $released = $this->stock->release($locked);

            $locked->update(['order_status' => 'cancelled']);

            return $released ? 'released' : 'nothing';
        });

        $message = match ($wasCancelled) {
            'released' => 'Pesanan dibatalkan dan bahan dikembalikan ke stok.',
            'nothing' => 'Pesanan dibatalkan. Pesanan ini dibuat sebelum stok bahan dicatat, jadi tidak ada bahan yang dikembalikan.',
            default => 'Pesanan tidak dapat dibatalkan (sudah dibatalkan atau selesai).',
        };

        return redirect()->route('admin.orders.show', $order)
            ->with('status', $message);
    }
}
