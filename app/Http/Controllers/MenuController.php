<?php

namespace App\Http\Controllers;

use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use App\Services\MenuAvailability;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(MenuAvailability $menuAvailability): View
    {
        $period = PoPeriod::currentlyOpen();

        $items = collect();
        $availability = [];
        $catalog = collect();

        if ($period) {
            // Lowest sort_order first; the first card is rendered as the wide featured card.
            $items = PoPeriodProduct::where('po_period_id', $period->id)
                ->whereHas('product', fn ($query) => $query->where('is_active', true))
                ->with('product')
                ->get()
                ->sortBy(fn (PoPeriodProduct $item) => [$item->product->sort_order, $item->product->id])
                ->values();

            $availability = $menuAvailability->forItems($items);
        } else {
            // No batch open: show the menu so people can look, without ordering.
            $catalog = Product::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        }

        // A tab only for a category that has menu (in this batch, or in the
        // catalog when no batch is open); the tab bar shows once there are two.
        $present = ($period ? $items->map(fn ($item) => $item->product) : $catalog)
            ->map(fn (Product $product) => $product->categoryKey())->unique();
        $tabs = array_values(array_filter(array_keys(Product::CATEGORIES), fn ($key) => $present->contains($key))) ?: ['matcha'];
        $asked = (string) request()->query('kategori');
        $activeTab = in_array($asked, $tabs, true) ? $asked : $tabs[0];

        view()->share('ycTheme', $activeTab === 'coffee' ? 'coffee' : 'matcha');
        view()->share('ycSwitchable', true);

        return view('menu.index', [
            'tabs' => $tabs,
            'showTabs' => count($tabs) > 1,
            'activeTab' => $activeTab,
            'period' => $period,
            'items' => $items,
            'availability' => $availability,
            'catalog' => $catalog,
        ]);
    }
}
