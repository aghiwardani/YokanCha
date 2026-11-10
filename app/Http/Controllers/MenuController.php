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
            $items = PoPeriodProduct::where('po_period_id', $period->id)
                ->with('product')
                ->get();
        }

        return view('menu.index', [
            'period' => $period,
            'items' => $items,
        ]);
    }
}
