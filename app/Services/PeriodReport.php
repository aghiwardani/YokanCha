<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Order;
use App\Models\PoPeriod;
use App\Models\PoPeriodCost;
use App\Models\PoPeriodIngredient;
use App\Models\Size;
use App\Models\SweetnessLevel;

/**
 * Everything that adds up one PO batch: bottles per variant, ingredients
 * used, HPP (COGS) and profit. Shared by the production sheet and the
 * reports so both always show the same numbers. Cancelled orders don't count.
 */
class PeriodReport
{
    public function __construct(private StockService $stock)
    {
    }

    /**
     * @return array{
     *   orders: \Illuminate\Support\Collection, cancelledCount: int, variants: list<array>,
     *   totals: array<int,float>, ingredients: \Illuminate\Support\Collection, stock: \Illuminate\Support\Collection,
     *   bottles: int, profit: array, byProduct: list<array>, bySize: list<array>, bySweetness: list<array>, buyers: list<array>
     * }
     */
    public function build(PoPeriod $period): array
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

        // Other costs: per bottle (packaging, labour) and per batch, shared
        // evenly over every bottle of the batch.
        $extraCosts = $period->costs()->get();
        $bottleCount = array_sum(array_column($variants, 'bottles'));
        $batchTotal = (int) $extraCosts->where('type', PoPeriodCost::PER_BATCH)->sum('amount');
        $batchShare = $bottleCount > 0 ? $batchTotal / $bottleCount : 0;
        $perBottleExtra = fn (string $size) => (int) $extraCosts
            ->filter(fn (PoPeriodCost $c) => $c->appliesToSize($size))->sum('amount');

        $ingredientCost = 0.0;
        $packagingCost = 0;
        foreach ($variants as &$variant) {
            [$variant['ingredientCost'], $variant['costComplete']] = $costOf($variant['ingredients']);
            $variant['extraCost'] = $perBottleExtra($variant['size']) * $variant['bottles'];
            $variant['cost'] = $variant['ingredientCost'] + $variant['extraCost'] + $batchShare * $variant['bottles'];
            $ingredientCost += $variant['ingredientCost'];
            $packagingCost += $variant['extraCost'];
        }
        unset($variant);

        [, $cogsComplete] = $costOf($totals);
        $cogs = $ingredientCost + $packagingCost + ($bottleCount > 0 ? $batchTotal : 0);
        $revenue = (int) $orders->sum(fn (Order $o) => $o->total());
        $paid = (int) $orders->where('payment_status', 'paid')->sum(fn (Order $o) => $o->total());
        $spent = (int) $stock->sum(fn (PoPeriodIngredient $row) => $row->cost ?? 0);
        $leftoverValue = $stock->sum(fn (PoPeriodIngredient $row) => $row->unitCost() === null ? 0 : max(0, $row->remaining()) * $row->unitCost());

        $profit = [
            'revenue' => $revenue,
            'paid' => $paid,
            'unpaid' => $revenue - $paid,
            'cogs' => (int) round($cogs),
            'cogsIngredients' => (int) round($ingredientCost),
            'cogsPerBottle' => $packagingCost,
            'cogsBatch' => $bottleCount > 0 ? $batchTotal : 0,
            'cogsPerUnit' => $bottleCount > 0 ? $cogs / $bottleCount : 0,
            'cogsComplete' => $cogsComplete,
            'gross' => $revenue - (int) round($cogs),
            'margin' => $revenue > 0 ? (int) round(($revenue - $cogs) / $revenue * 100) : 0,
            'spent' => $spent,
            'leftover' => (int) round($leftoverValue),
            'extraSpent' => $packagingCost + $batchTotal,
            'cash' => $revenue - $spent - $packagingCost - $batchTotal,
            'extraCosts' => $extraCosts,
            'missingPrices' => Ingredient::whereIn('id', $stock->filter(fn ($row) => $row->cost === null)->keys())
                ->orderBy('name')->pluck('name')->all(),
        ];

        return [
            'orders' => $orders,
            'cancelledCount' => Order::where('po_period_id', $period->id)->where('order_status', 'cancelled')->count(),
            'variants' => $variants,
            'totals' => $totals,
            'ingredients' => $ingredients,
            'stock' => $stock,
            'bottles' => $bottleCount,
            'profit' => $profit,
            'byProduct' => $this->rollUp($variants, 'product', fn ($v) => $v['sort']),
            'bySize' => $this->rollUp($variants, 'size', fn ($v) => $v['volume']),
            'bySweetness' => $this->rollUp($variants, 'sweetness', fn ($v) => $v['percent']),
            'buyers' => $orders->groupBy(fn (Order $o) => mb_strtolower(trim($o->customer_name)))
                ->map(fn ($group) => [
                    'name' => $group->first()->customer_name,
                    'orders' => $group->count(),
                    'bottles' => (int) $group->sum(fn (Order $o) => $o->items->sum('qty')),
                    'total' => (int) $group->sum(fn (Order $o) => $o->total()),
                ])
                ->sortByDesc('total')->values()->all(),
        ];
    }

    /**
     * Bottles, sales and HPP grouped by one field, biggest seller first.
     *
     * @return list<array{label:string, bottles:int, revenue:int, cost:float, profit:float}>
     */
    private function rollUp(array $variants, string $field, callable $sortKey): array
    {
        $rows = [];
        foreach ($variants as $v) {
            $label = ($v[$field] === '' || $v[$field] === '-') ? '-' : $v[$field];
            $rows[$label] ??= ['label' => $label, 'bottles' => 0, 'revenue' => 0, 'cost' => 0.0, 'sort' => $sortKey($v)];
            $rows[$label]['bottles'] += $v['bottles'];
            $rows[$label]['revenue'] += $v['revenue'];
            $rows[$label]['cost'] += $v['cost'];
        }

        $rows = array_map(fn ($r) => $r + ['profit' => $r['revenue'] - $r['cost']], array_values($rows));
        usort($rows, fn ($a, $b) => [$b['bottles'], $a['sort']] <=> [$a['bottles'], $b['sort']]);

        return $rows;
    }
}
