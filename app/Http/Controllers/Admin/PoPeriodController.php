<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\PoPeriod;
use App\Models\PoPeriodCost;
use App\Models\PoPeriodIngredient;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use App\Models\Size;
use App\Support\Number;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PoPeriodController extends Controller
{
    public function index(): View
    {
        $periods = PoPeriod::orderByDesc('created_at')->get();

        return view('admin.periods.index', [
            'periods' => $periods,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
        ]);

        DB::transaction(function () use ($validated) {
            $period = PoPeriod::create([
                'name' => $validated['name'],
                'opens_at' => $validated['opens_at'] ?? null,
                'closes_at' => $validated['closes_at'] ?? null,
                'status' => 'draft',
            ]);

            // A new batch starts with every menu; the owner unchecks what is not made.
            Product::where('is_active', true)->pluck('id')->each(fn ($productId) => PoPeriodProduct::create([
                'po_period_id' => $period->id,
                'product_id' => $productId,
            ]));
        });

        return redirect()->route('admin.periods')->with('status', 'Periode berhasil dibuat.');
    }

    public function open(PoPeriod $period): RedirectResponse
    {
        // Only one batch takes orders at a time: opening this one closes any other.
        DB::transaction(function () use ($period) {
            PoPeriod::where('status', 'open')
                ->whereKeyNot($period->getKey())
                ->update(['status' => 'closed']);

            $period->update(['status' => 'open']);
        });

        return redirect()->route('admin.periods')->with('status', 'Periode dibuka. Periode lain yang masih terbuka otomatis ditutup.');
    }

    public function close(PoPeriod $period): RedirectResponse
    {
        $period->update(['status' => 'closed']);

        return redirect()->route('admin.periods')->with('status', 'Periode ditutup.');
    }

    public function stock(Request $request, PoPeriod $period): View
    {
        $ingredients = Ingredient::orderBy('name')->get();
        $rows = $period->ingredientStocks()->get()->keyBy('ingredient_id');
        $previous = $this->previousPeriod($period);
        $leftovers = $this->leftovers($previous);

        // Per ingredient: leftover from the previous period that is used (default
        // all of it; set lower or to 0 when some went bad), and the new purchase.
        $carried = [];
        $bought = [];
        $boughtCost = [];
        foreach ($ingredients as $ingredient) {
            $row = $rows->get($ingredient->id);
            if ($row) {
                $carried[$ingredient->id] = (float) $row->carried;
                $bought[$ingredient->id] = $row->bought();
                $boughtCost[$ingredient->id] = $row->boughtCost();
            } else {
                $carried[$ingredient->id] = $leftovers[$ingredient->id]['amount'] ?? null;
                $bought[$ingredient->id] = null;
                $boughtCost[$ingredient->id] = null;
            }
        }

        $costLines = $period->costs()->get()
            ->map(fn (PoPeriodCost $c) => $c->only(['name', 'type', 'size', 'amount']))->all();

        // A period that was never saved starts from the previous period's leftovers
        // and other costs (shown in the form, saved only on Simpan).
        $prefilled = false;
        if ($previous && $rows->isEmpty() && $costLines === []) {
            $costLines = $previous->costs()->get()
                ->map(fn (PoPeriodCost $c) => $c->only(['name', 'type', 'size', 'amount']))->all();
            $prefilled = $costLines !== [] || $leftovers !== [];
        }

        return view('admin.periods.stock', [
            'period' => $period,
            'previous' => $previous,
            'leftovers' => $leftovers,
            'ingredients' => $ingredients,
            'rows' => $rows,
            'carried' => $carried,
            'bought' => $bought,
            'boughtCost' => $boughtCost,
            'costLines' => $costLines,
            'sizes' => Size::smallestFirst()->pluck('name')->all(),
            'prefilled' => $prefilled,
            'products' => Product::orderBy('sort_order')->orderBy('name')->get(),
            'selectedProducts' => $period->poPeriodProducts()->pluck('product_id')->all(),
        ]);
    }

    /**
     * The period created just before this one: its leftovers can move over.
     */
    private function previousPeriod(PoPeriod $period): ?PoPeriod
    {
        return PoPeriod::whereKeyNot($period->getKey())
            ->where(fn ($q) => $q->where('created_at', '<', $period->created_at)
                ->orWhere(fn ($q) => $q->where('created_at', $period->created_at)->where('id', '<', $period->id)))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<int,array{amount:float, unit:?float}> ingredient_id => what is left and its value per unit
     */
    private function leftovers(?PoPeriod $previous): array
    {
        if (! $previous) {
            return [];
        }

        $result = [];
        foreach ($previous->ingredientStocks()->get() as $row) {
            $left = round(max(0, $row->remaining()), 2);
            if ($left > 0) {
                $result[$row->ingredient_id] = ['amount' => $left, 'unit' => $row->unitCost()];
            }
        }

        return $result;
    }

    public function updateStock(Request $request, PoPeriod $period): RedirectResponse
    {
        // Amounts may arrive typed the Indonesian way ("2.000", "2,5").
        foreach (['stock', 'cost', 'carried', 'buy', 'buy_cost'] as $field) {
            if ($request->has($field)) {
                $request->merge([$field => Number::normalizeAll($request->input($field))]);
            }
        }
        if ($request->has('extra')) {
            $request->merge(['extra' => collect($request->input('extra'))
                ->map(fn ($line) => is_array($line) ? ['amount' => Number::normalize($line['amount'] ?? null)] + $line : $line)
                ->all()]);
        }

        $request->validate([
            'stock' => ['nullable', 'array'],
            'stock.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'cost' => ['nullable', 'array'],
            'cost.*' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'carried' => ['nullable', 'array'],
            'carried.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'buy' => ['nullable', 'array'],
            'buy.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'buy_cost' => ['nullable', 'array'],
            'buy_cost.*' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'extra' => ['nullable', 'array'],
            'extra.*.name' => ['nullable', 'string', 'max:100'],
            'extra.*.type' => ['nullable', 'in:'.PoPeriodCost::PER_BOTTLE.','.PoPeriodCost::PER_BATCH],
            'extra.*.size' => ['nullable', 'string', 'max:100'],
            'extra.*.amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'products' => ['nullable', 'array'],
            'products.*' => ['integer', 'exists:products,id'],
        ]);

        // Other costs: a fully blank row is skipped, a half-filled one is an error.
        $extra = [];
        $extraErrors = [];
        foreach ((array) $request->input('extra', []) as $i => $line) {
            $name = trim((string) ($line['name'] ?? ''));
            $amount = $line['amount'] ?? null;
            if ($name === '' && ($amount === null || $amount === '')) {
                continue;
            }
            if ($name === '') {
                $extraErrors["extra.{$i}.name"] = 'Isi nama biaya untuk nominal yang kamu masukkan.';
                continue;
            }
            if ($amount === null || $amount === '') {
                $extraErrors["extra.{$i}.amount"] = "Isi nominal biaya {$name}.";
                continue;
            }
            $type = ($line['type'] ?? '') === PoPeriodCost::PER_BATCH ? PoPeriodCost::PER_BATCH : PoPeriodCost::PER_BOTTLE;
            $size = trim((string) ($line['size'] ?? ''));
            $extra[] = [
                'name' => $name,
                'type' => $type,
                'size' => $type === PoPeriodCost::PER_BOTTLE && $size !== '' ? $size : null,
                'amount' => (int) $amount,
            ];
        }
        if ($extraErrors) {
            throw ValidationException::withMessages($extraErrors);
        }

        $plan = $this->stockPlan($request, $period);

        DB::transaction(function () use ($request, $period, $plan, $extra) {
            // The form always sends the whole list, so it replaces what was saved.
            if ($request->boolean('extra_sent')) {
                $period->costs()->delete();
                foreach ($extra as $line) {
                    $period->costs()->create($line);
                }
            }

            $existing = PoPeriodIngredient::where('po_period_id', $period->id)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('ingredient_id');
            $ingredients = Ingredient::whereIn('id', array_keys($plan))->get()->keyBy('id');

            // Stock can't go below what orders already use (a blank row counts as 0).
            $errors = [];
            foreach ($ingredients as $ingredient) {
                $row = $existing->get($ingredient->id);
                $line = $plan[$ingredient->id];
                if ($row && (float) $row->used > 0 && $line['stock'] < (float) $row->used) {
                    $used = rtrim(rtrim(number_format((float) $row->used, 2, ',', '.'), '0'), ',');
                    $errors[$line['field']] = "Stok {$ingredient->name} tidak boleh di bawah yang sudah terpakai ({$used} {$ingredient->unit}).";
                }
                if ($line['carriedTooMuch']) {
                    $errors["carried.{$ingredient->id}"] = "Sisa {$ingredient->name} dari periode lalu cuma {$line['carriedMax']} {$ingredient->unit}.";
                }
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            foreach ($ingredients as $ingredient) {
                $row = $existing->get($ingredient->id);
                $line = $plan[$ingredient->id];

                if ($line['blank']) {
                    $row?->delete();
                    continue;
                }

                $values = [
                    'stock' => $line['stock'],
                    'carried' => $line['carried'],
                    'carried_cost' => $line['carriedCost'],
                    'cost' => $line['cost'] === false ? $row?->cost : $line['cost'],
                ];

                if ($row) {
                    $row->update($values);
                } else {
                    PoPeriodIngredient::create($values + [
                        'po_period_id' => $period->id,
                        'ingredient_id' => $ingredient->id,
                        'used' => 0,
                    ]);
                }
            }

            $selected = collect($request->input('products', []))->map(fn ($id) => (int) $id)->unique();
            PoPeriodProduct::where('po_period_id', $period->id)
                ->whereNotIn('product_id', $selected)->delete();
            $have = PoPeriodProduct::where('po_period_id', $period->id)->pluck('product_id');
            foreach ($selected->diff($have) as $productId) {
                PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $productId]);
            }
        });

        return redirect()->route('admin.periods.stock', $period)->with('status', 'Stok bahan disimpan.');
    }

    /**
     * Turn the stock form into what to save per ingredient.
     *
     * Form: carried[id] (leftover used) + buy[id] (newly bought) and
     * buy_cost[id] (its price; blank = master price x amount). The older
     * single stock[id] / cost[id] fields still work and count as "all bought".
     * 'cost' => false means "leave the saved price alone".
     *
     * @return array<int,array{stock:float, carried:float, carriedCost:int, cost:int|null|false, blank:bool, field:string, carriedTooMuch:bool, carriedMax:string}>
     */
    private function stockPlan(Request $request, PoPeriod $period): array
    {
        $carriedIn = (array) $request->input('carried', []);
        $buyIn = (array) $request->input('buy', []);
        $buyCostIn = (array) $request->input('buy_cost', []);
        $stockIn = (array) $request->input('stock', []);
        $costIn = (array) $request->input('cost', []);

        $ids = array_values(array_unique(array_map('intval', array_merge(
            array_keys($carriedIn), array_keys($buyIn), array_keys($stockIn),
        ))));
        if ($ids === []) {
            return [];
        }

        $leftovers = $this->leftovers($this->previousPeriod($period));
        $saved = PoPeriodIngredient::where('po_period_id', $period->id)->get()->keyBy('ingredient_id');
        $masters = Ingredient::whereIn('id', $ids)->get()->keyBy('id');
        $filled = fn ($v) => $v !== null && $v !== '';
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');

        $plan = [];
        foreach ($ids as $id) {
            if (! $masters->has($id)) {
                continue;
            }
            $row = $saved->get($id);

            if (array_key_exists($id, $stockIn) && ! array_key_exists($id, $buyIn)) {
                $raw = $stockIn[$id];
                $plan[$id] = [
                    'stock' => $filled($raw) ? (float) $raw : 0.0,
                    'carried' => 0.0,
                    'carriedCost' => 0,
                    'cost' => array_key_exists($id, $costIn) ? ($filled($costIn[$id]) ? (int) $costIn[$id] : null) : false,
                    'blank' => ! $filled($raw),
                    'field' => "stock.{$id}",
                    'carriedTooMuch' => false,
                    'carriedMax' => '0',
                ];
                continue;
            }

            $carried = $filled($carriedIn[$id] ?? null) ? round((float) $carriedIn[$id], 2) : 0.0;
            $buy = $filled($buyIn[$id] ?? null) ? round((float) $buyIn[$id], 2) : 0.0;

            // At most what the previous period has left (or what was saved before).
            $max = max($leftovers[$id]['amount'] ?? 0, (float) ($row?->carried ?? 0));

            // Leftovers keep last period's value per unit.
            $prevUnit = $leftovers[$id]['unit']
                ?? ($row && (float) $row->carried > 0 ? $row->carried_cost / (float) $row->carried : null);
            $carriedCost = $carried > 0 && $prevUnit !== null ? (int) round($carried * $prevUnit) : 0;
            $carriedKnown = $carried <= 0 || $prevUnit !== null;

            // New purchase price: as typed, else master price x amount.
            $typed = $buyCostIn[$id] ?? null;
            $unitPrice = $masters[$id]->unitPrice();
            $buyCost = $filled($typed) ? (int) $typed
                : ($buy <= 0 ? 0 : ($unitPrice !== null ? (int) round($buy * $unitPrice) : null));

            $plan[$id] = [
                'stock' => round($carried + $buy, 2),
                'carried' => $carried,
                'carriedCost' => $carriedCost,
                'cost' => $carriedKnown && $buyCost !== null ? $carriedCost + $buyCost : null,
                'blank' => ! $filled($carriedIn[$id] ?? null) && ! $filled($buyIn[$id] ?? null),
                'field' => "buy.{$id}",
                'carriedTooMuch' => $carried > $max + 0.001,
                'carriedMax' => $fmt($max),
            ];
        }

        return $plan;
    }
}
