<?php

namespace App\Services;

use App\Models\PoPeriodProduct;

class CartService
{
    private const SESSION_KEY = 'cart';

    public function add(int $poPeriodProductId, int $qty, string $size, string $sweetness): void
    {
        $cart = session(self::SESSION_KEY, []);

        $lineKey = $this->lineKey($poPeriodProductId, $size, $sweetness);

        if (isset($cart[$lineKey])) {
            $cart[$lineKey]['qty'] += $qty;
        } else {
            $cart[$lineKey] = [
                'po_period_product_id' => $poPeriodProductId,
                'qty' => $qty,
                'size' => $size,
                'sweetness' => $sweetness,
            ];
        }

        session([self::SESSION_KEY => $cart]);
    }

    public function items(): array
    {
        $cart = session(self::SESSION_KEY, []);

        $lines = [];

        foreach ($cart as $lineKey => $line) {
            $poPeriodProduct = PoPeriodProduct::with('product')->find($line['po_period_product_id']);

            if (! $poPeriodProduct) {
                continue;
            }

            $lineTotal = $poPeriodProduct->product->base_price * $line['qty'];

            $lines[] = [
                'line_key' => $lineKey,
                'po_period_product' => $poPeriodProduct,
                'product' => $poPeriodProduct->product,
                'qty' => $line['qty'],
                'size' => $line['size'],
                'sweetness' => $line['sweetness'],
                'line_total' => $lineTotal,
            ];
        }

        return $lines;
    }

    public function update(string $lineKey, int $qty): void
    {
        $cart = session(self::SESSION_KEY, []);

        if (! isset($cart[$lineKey])) {
            return;
        }

        if ($qty <= 0) {
            unset($cart[$lineKey]);
        } else {
            $cart[$lineKey]['qty'] = $qty;
        }

        session([self::SESSION_KEY => $cart]);
    }

    public function remove(string $lineKey): void
    {
        $cart = session(self::SESSION_KEY, []);

        unset($cart[$lineKey]);

        session([self::SESSION_KEY => $cart]);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    private function lineKey(int $poPeriodProductId, string $size, string $sweetness): string
    {
        return "{$poPeriodProductId}|{$size}|{$sweetness}";
    }
}
