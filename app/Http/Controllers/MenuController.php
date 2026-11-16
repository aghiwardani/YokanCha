<?php

namespace App\Http\Controllers;

use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Services\StockService;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(StockService $stock): View
    {
        $period = PoPeriod::currentlyOpen();

        $items = collect();
        $availability = [];

        if ($period) {
            // Lowest sort_order first; the first card is rendered as the wide featured card.
            $items = PoPeriodProduct::where('po_period_id', $period->id)
                ->with('product')
                ->get()
                ->sortBy(fn (PoPeriodProduct $item) => [$item->product->sort_order, $item->product->id])
                ->values();

            foreach ($items as $item) {
                $availability[$item->id] = $this->availabilityFor($stock, $item);
            }
        }

        return view('menu.index', [
            'period' => $period,
            'items' => $items,
            'availability' => $availability,
        ]);
    }

    /**
     * @return array{max:int, sizes: array<string,int>, cells: array<string,array<string,int>>} sizes[size] = best available() over sweetness levels; cells[size][sweetness] = available()
     */
    private function availabilityFor(StockService $stock, PoPeriodProduct $item): array
    {
        $product = $item->product;
        $sizes = empty($product->size_options) ? ['-'] : array_values($product->size_options);
        $sweets = empty($product->sweetness_options) ? ['-'] : array_values($product->sweetness_options);

        $perSize = [];
        $cells = [];
        foreach ($sizes as $size) {
            $best = 0;
            foreach ($sweets as $sweet) {
                $count = $stock->available($item, (string) $size, (string) $sweet);
                $cells[$size][$sweet] = $count;
                $best = max($best, $count);
            }
            $perSize[$size] = $best;
        }

        return ['max' => $perSize === [] ? 0 : max($perSize), 'sizes' => $perSize, 'cells' => $cells];
    }
}
