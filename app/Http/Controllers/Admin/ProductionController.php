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
                    'revenue' => 0,
                    'ingredients' => [],
                ];
                $variants[$key]['bottles'] += $item->qty;
                $variants[$key]['revenue'] += $item->unit_price * $item->qty;

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

        $ingredients = Ingredient::whereIn('id', array_keys($totals))->orderBy('name')->get()->keyBy('id');
        $stock = PoPeriodIngredient::where('po_period_id', $period->id)->get()->keyBy('ingredient_id');

        // HPP: ingredients used, priced at what this period's stock cost per unit.
        $unitCost = $stock->map(fn (PoPeriodIngredient $row) => $row->unitCost());
        $costOf = function (array $amounts) use ($unitCost): array {
            $total = 0.0;
            $complete = true;
            foreach ($amounts as $ingredientId => $amount) {
                $unit = $unitCost[$ingredientId] ?? null;
                if ($unit === null) {
                    $complete = false;
                    continue;
                }
                $total += $amount * $unit;
            }

            return [$total, $complete];
        };

        foreach ($variants as &$variant) {
            [$variant['cost'], $variant['costComplete']] = $costOf($variant['ingredients']);
        }
        unset($variant);

        $products = collect($variants)->groupBy('product');

        [$cogs, $cogsComplete] = $costOf($totals);
        $revenue = (int) $orders->sum(fn (Order $o) => $o->total());
        $paid = (int) $orders->where('payment_status', 'paid')->sum(fn (Order $o) => $o->total());
        $spent = (int) $stock->sum(fn (PoPeriodIngredient $row) => $row->cost ?? 0);
        $leftoverValue = $stock->sum(fn (PoPeriodIngredient $row) => $row->unitCost() === null ? 0 : max(0, $row->remaining()) * $row->unitCost());

        $profit = [
            'revenue' => $revenue,
            'paid' => $paid,
            'cogs' => (int) round($cogs),
            'cogsComplete' => $cogsComplete,
            'gross' => $revenue - (int) round($cogs),
            'margin' => $revenue > 0 ? (int) round(($revenue - $cogs) / $revenue * 100) : 0,
            'spent' => $spent,
            'leftover' => (int) round($leftoverValue),
            'cash' => $revenue - $spent,
            'missingPrices' => Ingredient::whereIn('id', $stock->filter(fn ($row) => $row->cost === null)->keys())
                ->orderBy('name')->pluck('name')->all(),
        ];

        return view('admin.production.show', [
            'period' => $period,
            'orders' => $orders,
            'products' => $products,
            'totals' => $totals,
            'ingredients' => $ingredients,
            'stock' => $stock,
            'bottles' => array_sum(array_column($variants, 'bottles')),
            'profit' => $profit,
            'stillOpen' => $period->status === 'open' && (! $period->closes_at || $period->closes_at->isFuture()),
        ]);
    }
}
