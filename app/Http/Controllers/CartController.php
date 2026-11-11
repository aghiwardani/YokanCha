<?php

namespace App\Http\Controllers;

use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private CartService $cart)
    {
    }

    /**
     * Customers stay on the menu while adding bottles: the menu posts via fetch
     * and gets JSON back; without JavaScript they are sent back to #menu.
     */
    public function add(Request $request): RedirectResponse|JsonResponse
    {
        $fromMenu = url()->previous() === route('menu.index');

        try {
            $ppp = $this->addLine($request);
        } catch (ValidationException $e) {
            // Land back on the #menu section so the error is on screen, not the hero.
            if ($fromMenu) {
                $e->redirectTo(route('menu.index').'#menu');
            }

            throw $e;
        }

        $message = "{$ppp->product->name} masuk keranjang.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'count' => $this->cart->count(),
            ]);
        }

        if ($fromMenu) {
            return redirect()->to(route('menu.index').'#menu')->with('cart_status', $message);
        }

        return redirect()->route('cart.show');
    }

    private function addLine(Request $request): PoPeriodProduct
    {
        $data = $request->validate([
            'po_period_product_id' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:1'],
            'size' => ['required', 'string'],
            'sweetness' => ['required', 'string'],
        ]);

        $ppp = $this->openVariant((int) $data['po_period_product_id']);

        $this->assertOffered($data['size'], $ppp->product->size_options, 'size', 'ukuran');
        $this->assertOffered($data['sweetness'], $ppp->product->sweetness_options, 'sweetness', 'manis');

        $this->assertVariantLimits($ppp, $this->cart->variantQty($ppp->id) + (int) $data['qty']);

        $this->cart->add($ppp->id, (int) $data['qty'], $data['size'], $data['sweetness']);

        return $ppp;
    }

    public function show(): View
    {
        $lines = $this->cart->items();

        $subtotal = array_sum(array_column($lines, 'line_total'));

        return view('cart.show', [
            'lines' => $lines,
            'subtotal' => $subtotal,
        ]);
    }

    public function update(Request $request, string $lineKey): RedirectResponse
    {
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:0'],
        ]);

        $line = $this->cart->line($lineKey);

        if ($line && (int) $data['qty'] > 0) {
            $ppp = PoPeriodProduct::with('product')->find($line['po_period_product_id']);

            if ($ppp) {
                $this->assertVariantLimits($ppp, $this->cart->variantQty($ppp->id, $lineKey) + (int) $data['qty']);
            }
        }

        $this->cart->update($lineKey, (int) $data['qty']);

        return redirect()->route('cart.show');
    }

    public function remove(string $lineKey): RedirectResponse
    {
        $this->cart->remove($lineKey);

        return redirect()->route('cart.show');
    }

    /**
     * The variant must exist and belong to the batch that is open right now.
     */
    private function openVariant(int $poPeriodProductId): PoPeriodProduct
    {
        $ppp = PoPeriodProduct::with('product')->find($poPeriodProductId);
        $openPeriod = PoPeriod::currentlyOpen();

        if (! $ppp || ! $openPeriod || (int) $ppp->po_period_id !== (int) $openPeriod->id) {
            throw ValidationException::withMessages([
                'po_period_product_id' => 'Menu ini tidak ada di batch yang sedang buka.',
            ]);
        }

        return $ppp;
    }

    /**
     * $value must be one of the product's options; products without options
     * submit "-" (see the menu card).
     */
    private function assertOffered(string $value, ?array $options, string $field, string $label): void
    {
        $allowed = empty($options) ? ['-'] : $options;

        if (! in_array($value, $allowed, true)) {
            throw ValidationException::withMessages([
                $field => "Pilihan {$label} itu tidak tersedia untuk menu ini.",
            ]);
        }
    }

    /**
     * $variantTotal is how many bottles of this variant the cart would hold.
     */
    private function assertVariantLimits(PoPeriodProduct $ppp, int $variantTotal): void
    {
        $max = config('yokancha.max_per_variant');
        $name = $ppp->product->name;

        if ($variantTotal > $max) {
            throw ValidationException::withMessages([
                'qty' => "{$name} maksimal {$max} botol per pesanan.",
            ]);
        }

        $remaining = max(0, $ppp->remaining());

        if ($variantTotal > $remaining) {
            throw ValidationException::withMessages([
                'qty' => $remaining === 0
                    ? "{$name} sudah habis untuk batch ini."
                    : "Stok {$name} tinggal {$remaining} botol lagi.",
            ]);
        }
    }
}
