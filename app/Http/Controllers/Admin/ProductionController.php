<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\PoPeriod;
use App\Models\PoPeriodCost;
use App\Models\PoPeriodIngredient;
use App\Models\Size;
use App\Models\SweetnessLevel;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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

    /**
     * Re-work every live order of the batch from today's recipes, after a
     * recipe was changed in the master. Stock "used" follows, so what is left
     * stays right. Lines whose recipe is now empty keep their old amounts.
     */
    public function refreshRecipes(PoPeriod $period): RedirectResponse
    {
        $notes = DB::transaction(function () use ($period) {
            $rows = PoPeriodIngredient::where('po_period_id', $period->id)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('ingredient_id');

            $orders = Order::where('po_period_id', $period->id)
                ->where('order_status', '!=', 'cancelled')
                ->with('items.product', 'items.ingredientUsages')
                ->get();

            $kept = [];
            $used = [];
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $perBottle = $item->product
                        ? $this->stock->perBottle($item->product, (string) $item->size, (string) $item->sweetness)
                        : [];

                    if ($perBottle === []) {
                        $kept[] = ($item->product?->name ?? 'Produk dihapus').' '.$item->size;
                        $usage = $item->ingredientUsages->mapWithKeys(fn ($u) => [$u->ingredient_id => (float) $u->amount])->all();
                    } else {
                        $usage = array_map(fn ($a) => round($a * $item->qty, 2), $perBottle);
                        $item->ingredientUsages()->delete();
                        foreach ($usage as $ingredientId => $amount) {
                            $item->ingredientUsages()->create(['ingredient_id' => $ingredientId, 'amount' => $amount]);
                        }
                    }

                    foreach ($usage as $ingredientId => $amount) {
                        $used[$ingredientId] = ($used[$ingredientId] ?? 0) + $amount;
                    }
                }
            }

            foreach ($rows as $ingredientId => $row) {
                $row->update(['used' => round($used[$ingredientId] ?? 0, 2)]);
            }
            // A recipe may now use an ingredient this period has no stock row for.
            $noStock = [];
            foreach (array_diff_key($used, $rows->all()) as $ingredientId => $amount) {
                PoPeriodIngredient::create([
                    'po_period_id' => $period->id, 'ingredient_id' => $ingredientId, 'stock' => 0, 'used' => round($amount, 2),
                ]);
                $noStock[] = $ingredientId;
            }

            $short = PoPeriodIngredient::where('po_period_id', $period->id)->get()
                ->filter(fn (PoPeriodIngredient $row) => $row->remaining() < 0)->pluck('ingredient_id')->all();

            return [
                'kept' => array_values(array_unique($kept)),
                'short' => Ingredient::whereIn('id', array_unique(array_merge($short, $noStock)))->orderBy('name')->pluck('name')->all(),
            ];
        });

        $message = 'Takaran pesanan dihitung ulang dari resep terbaru.';
        if ($notes['kept']) {
            $message .= ' Resep belum diisi untuk '.implode(', ', $notes['kept']).', jadi takaran lamanya dipakai.';
        }
        if ($notes['short']) {
            $message .= ' Stok kurang untuk: '.implode(', ', $notes['short']).'. Tambah stoknya di Stok bahan.';
        }

        return redirect()->route('admin.periods.production', $period)->with('status', $message);
    }

    /**
     * Fill this period's purchase cost from the master price: stock x price per unit.
     */
    public function refreshPrices(PoPeriod $period): RedirectResponse
    {
        $rows = PoPeriodIngredient::where('po_period_id', $period->id)->with('ingredient')->get();
        $updated = 0;
        $missing = [];

        foreach ($rows as $row) {
            $unit = $row->ingredient?->unitPrice();
            if ($unit === null) {
                $missing[] = $row->ingredient?->name;
                continue;
            }
            $row->update(['cost' => (int) round((float) $row->stock * $unit)]);
            $updated++;
        }

        $message = "Harga beli {$updated} bahan diisi dari harga master.";
        $missing = array_filter($missing);
        if ($missing) {
            sort($missing);
            $message .= ' Belum ada harga di master untuk: '.implode(', ', $missing).'.';
        }

        return redirect()->route('admin.periods.production', $period)->with('status', $message);
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

        $products = collect($variants)->groupBy('product');

        [, $cogsComplete] = $costOf($totals);
        $cogs = $ingredientCost + $packagingCost + ($bottleCount > 0 ? $batchTotal : 0);
        $revenue = (int) $orders->sum(fn (Order $o) => $o->total());
        $paid = (int) $orders->where('payment_status', 'paid')->sum(fn (Order $o) => $o->total());
        $spent = (int) $stock->sum(fn (PoPeriodIngredient $row) => $row->cost ?? 0);
        $leftoverValue = $stock->sum(fn (PoPeriodIngredient $row) => $row->unitCost() === null ? 0 : max(0, $row->remaining()) * $row->unitCost());

        $profit = [
            'revenue' => $revenue,
            'paid' => $paid,
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
