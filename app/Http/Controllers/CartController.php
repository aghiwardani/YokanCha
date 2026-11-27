<?php

namespace App\Http\Controllers;

use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Services\CartService;
use App\Services\MenuAvailability;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private CartService $cart, private StockService $stock)
    {
    }

    /**
     * Customers stay on the menu while adding bottles: the menu posts via fetch
     * and gets JSON back; without JavaScript they are sent back to #menu.
     */
    public function add(Request $request, MenuAvailability $menuAvailability): RedirectResponse|JsonResponse
    {
        // The menu may carry ?kategori=coffee; send them back to that same tab.
        $previous = strtok(url()->previous(), '#');
        $fromMenu = strtok($previous, '?') === route('menu.index');
        $backToMenu = $previous.'#menu';

        try {
            $ppp = $this->addLine($request);
        } catch (ValidationException $e) {
            // Land back on the #menu section so the error is on screen, not the hero.
            if ($fromMenu) {
                $e->redirectTo($backToMenu);
            }

            throw $e;
        }

        $qty = (int) $request->input('qty');
        $message = ($qty > 1 ? "{$qty} botol " : '')."{$ppp->product->name}"
            .($request->boolean('extra_matcha') ? ' extra matcha' : '').' masuk keranjang.';

        if ($request->expectsJson()) {
            // Fresh stock for every card in the batch: ingredients are shared,
            // so adding one menu can run another one out too.
            $items = PoPeriodProduct::where('po_period_id', $ppp->po_period_id)
                ->whereHas('product', fn ($query) => $query->where('is_active', true))
                ->with('product')
                ->get();

            return response()->json([
                'message' => $message,
                'count' => $this->cart->count(),
                'availability' => $menuAvailability->forItems($items),
            ]);
        }

        if ($fromMenu) {
            return redirect()->to($backToMenu)->with('cart_status', $message);
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
            'extra_matcha' => ['nullable', 'boolean'],
        ]);

        $ppp = $this->openVariant((int) $data['po_period_product_id']);

        $this->assertOffered($data['size'], $ppp->product->size_options, 'size', 'ukuran');
        $this->assertOffered($data['sweetness'], $ppp->product->sweetness_options, 'sweetness', 'manis');

        $extra = $request->boolean('extra_matcha');
        if ($extra && $ppp->product->extraMatchaPrice($data['size']) <= 0) {
            throw ValidationException::withMessages([
                'extra_matcha' => "Extra matcha belum ada untuk {$ppp->product->name} ukuran {$data['size']}.",
            ]);
        }

        $lineKey = CartService::lineKey($ppp->id, $data['size'], $data['sweetness'], $extra);
        $lineQty = (int) ($this->cart->line($lineKey)['qty'] ?? 0) + (int) $data['qty'];

        $this->assertVariantLimits(
            $ppp,
            $data['size'],
            $data['sweetness'],
            $lineQty,
            $this->cart->variantQty($ppp->id) + (int) $data['qty'],
            null,
            $extra,
        );

        $this->cart->add($ppp->id, (int) $data['qty'], $data['size'], $data['sweetness'], $extra);

        return $ppp;
    }

    public function show(): View
    {
        $lines = $this->cart->items();

        $subtotal = array_sum(array_column($lines, 'line_total'));

        view()->share('ycTheme', \App\Models\Product::themeFor(array_column($lines, 'product')));
        view()->share('ycSwitchable', false);

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
                $this->assertVariantLimits(
                    $ppp,
                    $line['size'],
                    $line['sweetness'],
                    (int) $data['qty'],
                    $this->cart->variantQty($ppp->id, $lineKey) + (int) $data['qty'],
                    $lineKey,
                    (bool) ($line['extra'] ?? false),
                );
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

        if (! $ppp->product->is_active) {
            throw ValidationException::withMessages([
                'po_period_product_id' => "{$ppp->product->name} sedang tidak dijual.",
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
     * $variantTotal is how many bottles of this variant the cart would hold;
     * $lineQty is what the (ppp, size, sweetness) cart line would end up with.
     * Ingredient stock is checked after subtracting every other cart line.
     */
    private function assertVariantLimits(
        PoPeriodProduct $ppp,
        string $size,
        string $sweetness,
        int $lineQty,
        int $variantTotal,
        ?string $exceptLineKey,
        bool $extra = false,
    ): void {
        $max = config('yokancha.max_per_variant');
        $name = $ppp->product->name;

        if ($variantTotal > $max) {
            throw ValidationException::withMessages([
                'qty' => "{$name} maksimal {$max} botol per pesanan.",
            ]);
        }

        $thisKey = CartService::lineKey($ppp->id, $size, $sweetness, $extra);
        $others = $this->stock->need(array_values(array_filter(
            $this->cart->items(),
            fn (array $line) => $line['line_key'] !== $thisKey && $line['line_key'] !== $exceptLineKey,
        )));

        $canMake = $this->stock->available($ppp, $size, $sweetness, $others, $extra);
        $what = $extra ? "{$name} extra matcha" : $name;

        if ($lineQty > $canMake) {
            throw ValidationException::withMessages([
                'qty' => $canMake === 0
                    ? "{$what} sudah habis untuk batch ini."
                    : "Stok {$what} ukuran {$size} tinggal cukup buat {$canMake} botol lagi.",
            ]);
        }
    }
}
