<?php

namespace App\Http\Controllers;

use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(): View
    {
        $period = PoPeriod::where('status', 'open')
            ->where(function ($query) {
                $query->whereNull('opens_at')->orWhere('opens_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('closes_at')->orWhere('closes_at', '>=', now());
            })
            ->first();

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
