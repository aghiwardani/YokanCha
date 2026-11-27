<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\PoPeriod;
use App\Services\CartService;
use App\Services\StockService;
use App\Support\Phone;
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
        private StockService $stock,
    ) {
    }

    public function show(): View|RedirectResponse
    {
        $lines = $this->cart->items();

        if (! empty($lines)) {
            $this->assertCartMatchesOpenPeriod($lines);
        }

        $subtotal = array_sum(array_column($lines, 'line_total'));

        view()->share('ycTheme', \App\Models\Product::themeFor(array_column($lines, 'product')));
        view()->share('ycSwitchable', false);

        return view('checkout.show', [
            'lines' => $lines,
            'subtotal' => $subtotal,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_whatsapp' => [
                'required',
                'string',
                'max:30',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! Phone::looksValid((string) $value)) {
                        $fail('Nomor WhatsApp belum benar. Contoh: 081234567890.');
                    }
                },
            ],
        ]);

        $lines = $this->cart->items();

        if (empty($lines)) {
            throw ValidationException::withMessages([
                'cart' => 'Keranjangmu masih kosong.',
            ]);
        }

        $openPeriod = $this->assertCartMatchesOpenPeriod($lines);

        $poPeriodId = $openPeriod->id;

        $order = DB::transaction(function () use ($data, $lines, $poPeriodId) {
            try {
                $usages = $this->stock->reserve($poPeriodId, $lines);
            } catch (InsufficientStockException $e) {
                throw ValidationException::withMessages([
                    'quota' => "Stok bahan buat {$e->productName} tidak cukup. Kurangi jumlahnya atau hapus dari keranjang.",
                ]);
            }

            $order = Order::create([
                'po_period_id' => $poPeriodId,
                'customer_name' => $data['customer_name'],
                'customer_whatsapp' => $data['customer_whatsapp'],
                'order_number' => $this->generateOrderNumber($poPeriodId),
            ]);

            foreach ($lines as $index => $line) {
                $item = $order->items()->create([
                    'product_id' => $line['product']->id,
                    'size' => $line['size'],
                    'sweetness' => $line['sweetness'],
                    'extra_matcha' => $line['extra'] ?? false,
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                ]);

                foreach ($usages[$index] ?? [] as $ingredientId => $amount) {
                    $item->ingredientUsages()->create([
                        'ingredient_id' => $ingredientId,
                        'amount' => $amount,
                    ]);
                }
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

        view()->share('ycTheme', \App\Models\Product::themeFor($order->items->pluck('product')));
        view()->share('ycSwitchable', false);

        return view('checkout.confirmation', [
            'order' => $order,
            'total' => $total,
            'whatsappUrl' => 'https://wa.me/'.Phone::normalize(config('yokancha.whatsapp'))
                .'?text='.rawurlencode($this->whatsappMessage($order, $total)),
        ]);
    }

    /**
     * Prefilled order message for the seller (Figma "Cara pesan" step 03:
     * "Pesan rapi terisi otomatis. Tinggal tekan kirim.").
     */
    private function whatsappMessage(Order $order, int $total): string
    {
        $rupiah = fn (int $amount) => 'Rp'.number_format($amount, 0, ',', '.');

        $lines = [
            'Halo, aku mau konfirmasi pesanan PO YokanCha.',
            '',
            "No. pesanan: {$order->order_number}",
            "Nama: {$order->customer_name}",
            "WhatsApp: {$order->customer_whatsapp}",
            '',
        ];

        foreach ($order->items as $item) {
            $options = $item->variantLabel();

            $lines[] = "- {$item->qty}x {$item->product->name}"
                .($options ? " ({$options})" : '')
                .' = '.$rupiah($item->qty * $item->unit_price);
        }

        $lines[] = '';
        $lines[] = 'Total: '.$rupiah($total);

        return implode("\n", $lines);
    }

    /**
     * Ensure the batch (PO period) the cart's items belong to is still the
     * currently-open one. Rejects if the period has since closed, or if any
     * cart line belongs to a period other than the one currently open.
     */
    private function assertCartMatchesOpenPeriod(array $lines): PoPeriod
    {
        $openPeriod = PoPeriod::currentlyOpen();

        if (! $openPeriod) {
            throw ValidationException::withMessages([
                'cart' => 'Pre-order batch ini sudah tutup.',
            ]);
        }

        foreach ($lines as $line) {
            if ((int) $line['po_period_product']->po_period_id !== (int) $openPeriod->id) {
                throw ValidationException::withMessages([
                    'cart' => 'Ada item dari batch yang sudah tutup. Hapus dulu, lalu pilih lagi dari menu sekarang.',
                ]);
            }

            if (! $line['product']->is_active) {
                throw ValidationException::withMessages([
                    'cart' => "{$line['product']->name} sudah tidak dijual. Hapus dari keranjang dulu, lalu lanjut pesan.",
                ]);
            }
        }

        return $openPeriod;
    }

    private function generateOrderNumber(int $poPeriodId): string
    {
        return Order::newNumber($poPeriodId);
    }
}
