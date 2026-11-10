<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private CartService $cart)
    {
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'po_period_product_id' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:1'],
            'size' => ['required', 'string'],
            'sweetness' => ['required', 'string'],
        ]);

        $this->cart->add(
            (int) $data['po_period_product_id'],
            (int) $data['qty'],
            $data['size'],
            $data['sweetness'],
        );

        return redirect()->route('cart.show');
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

    public function remove(string $lineKey): RedirectResponse
    {
        $this->cart->remove($lineKey);

        return redirect()->route('cart.show');
    }
}
