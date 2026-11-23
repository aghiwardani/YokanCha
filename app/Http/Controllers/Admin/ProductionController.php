<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\PoPeriod;
use App\Models\PoPeriodIngredient;
use App\Services\PeriodReport;
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
     * Price this period's new purchases at the master price (amount bought x
     * price per unit). Leftovers carried in keep the value they came with.
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
            $row->update(['cost' => (int) $row->carried_cost + (int) round($row->bought() * $unit)]);
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

    public function show(PoPeriod $period, PeriodReport $report): View
    {
        $data = $report->build($period);

        return view('admin.production.show', [
            'period' => $period,
            'orders' => $data['orders'],
            'products' => collect($data['variants'])->groupBy('product'),
            'totals' => $data['totals'],
            'ingredients' => $data['ingredients'],
            'stock' => $data['stock'],
            'bottles' => $data['bottles'],
            'profit' => $data['profit'],
            'stillOpen' => $period->status === 'open' && (! $period->closes_at || $period->closes_at->isFuture()),
        ]);
    }
}
