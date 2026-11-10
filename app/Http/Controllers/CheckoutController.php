<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CartService;
use App\Services\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private QuotaService $quota,
    ) {
    }

    public function show(): View|RedirectResponse
    {
        $lines = $this->cart->items();

        $subtotal = array_sum(array_column($lines, 'line_total'));

        return view('checkout.show', [
            'lines' => $lines,
            'subtotal' => $subtotal,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_whatsapp' => ['required', 'string', 'max:30'],
        ]);

        $lines = $this->cart->items();

        if (empty($lines)) {
            throw ValidationException::withMessages([
                'cart' => 'Keranjang kamu kosong.',
            ]);
        }

        $poPeriodId = $lines[0]['po_period_product']->po_period_id;

        $order = DB::transaction(function () use ($data, $lines, $poPeriodId) {
            foreach ($lines as $line) {
                $ppp = $line['po_period_product'];

                if (! $this->quota->reserve($ppp, $line['qty'])) {
                    throw ValidationException::withMessages([
                        'quota' => "Stok untuk {$line['product']->name} tidak cukup. Silakan kurangi jumlah atau hapus item dari keranjang.",
                    ]);
                }
            }

            $order = Order::create([
                'po_period_id' => $poPeriodId,
                'customer_name' => $data['customer_name'],
                'customer_whatsapp' => $data['customer_whatsapp'],
                'order_number' => $this->generateOrderNumber($poPeriodId),
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'size' => $line['size'],
                    'sweetness' => $line['sweetness'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['product']->base_price,
                ]);
            }

            return $order;
        });

        $this->cart->clear();

        return redirect()->route('checkout.confirmation', $order->order_number);
    }

    public function confirmation(string $orderNumber): View
    {
        $order = Order::where('order_number', $orderNumber)
            ->with('items.product')
            ->firstOrFail();

        $total = $order->items->sum(fn ($item) => $item->qty * $item->unit_price);

        return view('checkout.confirmation', [
            'order' => $order,
            'total' => $total,
        ]);
    }

    private function generateOrderNumber(int $poPeriodId): string
    {
        do {
            $candidate = sprintf('YC-%d-%s', $poPeriodId, Str::upper(Str::random(6)));
        } while (Order::where('order_number', $candidate)->exists());

        return $candidate;
    }
}
