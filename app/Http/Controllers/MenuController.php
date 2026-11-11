<?php

namespace App\Http\Controllers;

use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(): View
    {
        $period = PoPeriod::currentlyOpen();

        $items = collect();

        if ($period) {
            // Lowest sort_order first; the first card is rendered as the wide featured card.
            $items = PoPeriodProduct::where('po_period_id', $period->id)
                ->with('product')
                ->get()
                ->sortBy(fn (PoPeriodProduct $item) => [$item->product->sort_order, $item->product->id])
                ->values();
        }

        return view('menu.index', [
            'period' => $period,
            'items' => $items,
        ]);
    }
}
