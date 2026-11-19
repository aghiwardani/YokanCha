<?php

namespace App\Http\Controllers;

use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Services\MenuAvailability;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(MenuAvailability $menuAvailability): View
    {
        $period = PoPeriod::currentlyOpen();

        $items = collect();
        $availability = [];

        if ($period) {
            // Lowest sort_order first; the first card is rendered as the wide featured card.
            $items = PoPeriodProduct::where('po_period_id', $period->id)
                ->whereHas('product', fn ($query) => $query->where('is_active', true))
                ->with('product')
                ->get()
                ->sortBy(fn (PoPeriodProduct $item) => [$item->product->sort_order, $item->product->id])
                ->values();

            $availability = $menuAvailability->forItems($items);
        }

        return view('menu.index', [
            'period' => $period,
            'items' => $items,
            'availability' => $availability,
        ]);
    }
}
