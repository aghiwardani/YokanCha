<?php

namespace App\Services;

use App\Models\PoPeriodProduct;

/**
 * How many more bottles this visitor can still add, per menu card and per
 * size/sweetness. Ingredients already sitting in the visitor's cart are
 * taken off first, so a card shows "Stok habis" as soon as the cart holds
 * everything that is left.
 */
class MenuAvailability
{
    public function __construct(private StockService $stock, private CartService $cart)
    {
    }

    /**
     * @param iterable<PoPeriodProduct> $items products of one open period
     * @return array<int,array{max:int, stock:int, limit:int, sizes:array<string,int>, cells:array<string,array<string,int>>}>
     *   keyed by po_period_product id. cells[size][sweetness] = bottles that can still be added,
     *   sizes[size] = best of its cells, stock = most bottles any variant can still be made,
     *   limit = bottles left under the per-order cap, max = min(stock, limit).
     */
    public function forItems(iterable $items): array
    {
        $result = [];
        $needByPeriod = [];
        $cap = (int) config('yokancha.max_per_variant');

        foreach ($items as $item) {
            $periodId = $item->po_period_id;
            $needByPeriod[$periodId] ??= $this->stock->need(array_values(array_filter(
                $this->cart->items(),
                fn (array $line) => $line['po_period_product']->po_period_id === $periodId,
            )));

            $product = $item->product;
            $sizes = empty($product->size_options) ? ['-'] : array_values($product->size_options);
            $sweets = empty($product->sweetness_options) ? ['-'] : array_values($product->sweetness_options);

            $cells = [];
            $perSize = [];
            foreach ($sizes as $size) {
                $best = 0;
                foreach ($sweets as $sweet) {
                    $count = $this->stock->available($item, (string) $size, (string) $sweet, $needByPeriod[$periodId]);
                    $cells[$size][$sweet] = $count;
                    $best = max($best, $count);
                }
                $perSize[$size] = $best;
            }

            $stockMax = $perSize === [] ? 0 : max($perSize);
            $limit = max(0, $cap - $this->cart->variantQty($item->id));

            $result[$item->id] = [
                'max' => min($stockMax, $limit),
                'stock' => $stockMax,
                'limit' => $limit,
                'sizes' => $perSize,
                'cells' => $cells,
            ];
        }

        return $result;
    }
}
