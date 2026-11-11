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

            $unitPrice = $poPeriodProduct->product->priceFor($line['size']);

            $lines[] = [
                'line_key' => $lineKey,
                'po_period_product' => $poPeriodProduct,
                'product' => $poPeriodProduct->product,
                'qty' => $line['qty'],
                'size' => $line['size'],
                'sweetness' => $line['sweetness'],
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $line['qty'],
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

    /**
     * Raw session line for $lineKey, or null if it isn't in the cart.
     *
     * @return array{po_period_product_id: int, qty: int, size: string, sweetness: string}|null
     */
    public function line(string $lineKey): ?array
    {
        return session(self::SESSION_KEY, [])[$lineKey] ?? null;
    }

    /**
     * Bottles of one variant (PoPeriodProduct) in the cart across all sizes and
     * sweetness levels, optionally ignoring one line (used when that line's qty
     * is about to be replaced).
     */
    public function variantQty(int $poPeriodProductId, ?string $exceptLineKey = null): int
    {
        $total = 0;

        foreach (session(self::SESSION_KEY, []) as $lineKey => $line) {
            if ($lineKey !== $exceptLineKey && (int) $line['po_period_product_id'] === $poPeriodProductId) {
                $total += $line['qty'];
            }
        }

        return $total;
    }

    /**
     * Total number of bottles in the cart (sum of line quantities).
     */
    public function count(): int
    {
        return (int) array_sum(array_column(session(self::SESSION_KEY, []), 'qty'));
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
