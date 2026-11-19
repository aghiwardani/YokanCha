<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\PoPeriod;
use App\Models\PoPeriodIngredient;
use App\Models\Size;
use App\Models\SweetnessLevel;
use App\Services\StockService;
use Illuminate\View\View;

/**
 * Production sheet for one PO batch: what to make (bottles per variant), the
 * recipe per bottle, the total of each ingredient, and who picks up what.
 * Built from the batch's live (not cancelled) orders.
 */
class ProductionController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    public function show(PoPeriod $period): View
    {
        $orders = Order::query()
            ->where('po_period_id', $period->id)
            ->where('order_status', '!=', 'cancelled')
            ->with('items.product', 'items.ingredientUsages')
            ->orderBy('customer_name')
            ->get();

        $sizeVolumes = Size::pluck('volume_ml', 'name')->all();
        $percentMap = SweetnessLevel::percentMap();

        $variants = [];
        $totals = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $size = (string) $item->size;
                $sweetness = (string) $item->sweetness;
                $key = $item->product_id.'|'.$size.'|'.$sweetness;

                $variants[$key] ??= [
                    'product' => $item->product?->name ?? 'Produk dihapus',
                    'sort' => [$item->product?->sort_order ?? PHP_INT_MAX, $item->product?->name ?? ''],
                    'size' => $size,
                    'sweetness' => $sweetness,
                    'volume' => Size::sortVolume($sizeVolumes[$size] ?? Size::volumeFromName($size)),
                    'percent' => SweetnessLevel::percentFor($sweetness, $percentMap),
                    'bottles' => 0,
                    'ingredients' => [],
                ];
                $variants[$key]['bottles'] += $item->qty;

                // What was reserved when the order was placed; orders from
                // before stock tracking fall back to today's recipe.
                $usages = $item->ingredientUsages->isNotEmpty()
                    ? $item->ingredientUsages->mapWithKeys(fn ($u) => [$u->ingredient_id => (float) $u->amount])->all()
                    : ($item->product
                        ? array_map(fn ($a) => $a * $item->qty, $this->stock->perBottle($item->product, $size, $sweetness))
                        : []);

                foreach ($usages as $ingredientId => $amount) {
                    $variants[$key]['ingredients'][$ingredientId] = ($variants[$key]['ingredients'][$ingredientId] ?? 0) + $amount;
                    $totals[$ingredientId] = ($totals[$ingredientId] ?? 0) + $amount;
                }
            }
        }

        usort($variants, fn ($a, $b) => [$a['sort'], $a['volume'], $a['percent'], $a['size'], $a['sweetness']]
            <=> [$b['sort'], $b['volume'], $b['percent'], $b['size'], $b['sweetness']]);

        $products = collect($variants)->groupBy('product');

        $ingredients = Ingredient::whereIn('id', array_keys($totals))->orderBy('name')->get()->keyBy('id');
        $stock = PoPeriodIngredient::where('po_period_id', $period->id)->get()->keyBy('ingredient_id');

        return view('admin.production.show', [
            'period' => $period,
            'orders' => $orders,
            'products' => $products,
            'totals' => $totals,
            'ingredients' => $ingredients,
            'stock' => $stock,
            'bottles' => array_sum(array_column($variants, 'bottles')),
            'stillOpen' => $period->status === 'open' && (! $period->closes_at || $period->closes_at->isFuture()),
        ]);
    }
}
