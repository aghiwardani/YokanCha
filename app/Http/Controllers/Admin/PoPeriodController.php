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

        $values = [];
        $costs = [];
        foreach ($ingredients as $ingredient) {
            $row = $rows->get($ingredient->id);
            $values[$ingredient->id] = $row ? $row->stock : null;
            $costs[$ingredient->id] = $row?->cost;
        }

        $costLines = $period->costs()->get()
            ->map(fn (PoPeriodCost $c) => $c->only(['name', 'type', 'size', 'amount']))->all();

        $prefilled = $request->query('prefill') === 'previous';
        if ($prefilled) {
            $previous = PoPeriod::whereKeyNot($period->getKey())
                ->orderByDesc('created_at')->orderByDesc('id')->first();

            if ($previous) {
                if ($costLines === []) {
                    $costLines = $previous->costs()->get()
                        ->map(fn (PoPeriodCost $c) => $c->only(['name', 'type', 'size', 'amount']))->all();
                }

                foreach ($previous->ingredientStocks()->get() as $prev) {
                    if (array_key_exists($prev->ingredient_id, $values)) {
                        $left = max(0, $prev->remaining());
                        $values[$prev->ingredient_id] = $left;
                        // Leftovers carry their share of what they cost last time.
                        $unit = $prev->unitCost();
                        $costs[$prev->ingredient_id] = $unit === null ? null : (int) round($left * $unit);
                    }
                }
            }
        }

        return view('admin.periods.stock', [
            'period' => $period,
            'ingredients' => $ingredients,
            'rows' => $rows,
            'values' => $values,
            'costs' => $costs,
            'costLines' => $costLines,
            'sizes' => Size::smallestFirst()->pluck('name')->all(),
            'prefilled' => $prefilled,
            'products' => Product::orderBy('sort_order')->orderBy('name')->get(),
            'selectedProducts' => $period->poPeriodProducts()->pluck('product_id')->all(),
        ]);
    }

    public function updateStock(Request $request, PoPeriod $period): RedirectResponse
    {
        // Stock may arrive typed the Indonesian way ("2.000", "2,5").
        if ($request->has('stock')) {
            $request->merge(['stock' => Number::normalizeAll($request->input('stock'))]);
        }
        if ($request->has('extra')) {
            $request->merge(['extra' => collect($request->input('extra'))
                ->map(fn ($line) => is_array($line) ? ['amount' => Number::normalize($line['amount'] ?? null)] + $line : $line)
                ->all()]);
        }
        if ($request->has('cost')) {
            $request->merge(['cost' => Number::normalizeAll($request->input('cost'))]);
        }

        $request->validate([
            'stock' => ['nullable', 'array'],
            'stock.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'cost' => ['nullable', 'array'],
            'cost.*' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
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

        $input = $request->input('stock', []);
        $costInput = $request->input('cost', []);

        DB::transaction(function () use ($request, $period, $input, $costInput, $extra) {
            // The form always sends the whole list, so it replaces what was saved.
            if ($request->boolean('extra_sent')) {
                $period->costs()->delete();
                foreach ($extra as $line) {
                    $period->costs()->create($line);
                }
            }

            $existing = PoPeriodIngredient::where('po_period_id', $period->id)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('ingredient_id');
            $ingredients = Ingredient::whereIn('id', array_keys($input))->get()->keyBy('id');

            // A blank input counts as 0, so it is rejected too when stock is already used.
            $errors = [];
            foreach ($ingredients as $ingredient) {
                $row = $existing->get($ingredient->id);
                $raw = $input[$ingredient->id] ?? null;
                $value = ($raw === null || $raw === '') ? 0.0 : (float) $raw;

                if ($row && (float) $row->used > 0 && $value < (float) $row->used) {
                    $used = (float) $row->used;
                    $errors["stock.{$ingredient->id}"] = "Stok {$ingredient->name} tidak boleh di bawah yang sudah terpakai ({$used} {$ingredient->unit}).";
                }
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            foreach ($ingredients as $ingredient) {
                $row = $existing->get($ingredient->id);
                $raw = $input[$ingredient->id] ?? null;

                if ($raw === null || $raw === '') {
                    $row?->delete();
                    continue;
                }

                // A form without the price field leaves the saved price alone.
                $rawCost = $costInput[$ingredient->id] ?? null;
                $cost = array_key_exists($ingredient->id, $costInput)
                    ? (($rawCost === null || $rawCost === '') ? null : (int) $rawCost)
                    : $row?->cost;

                if ($row) {
                    $row->update(['stock' => $raw, 'cost' => $cost]);
                } else {
                    PoPeriodIngredient::create([
                        'po_period_id' => $period->id,
                        'ingredient_id' => $ingredient->id,
                        'stock' => $raw,
                        'used' => 0,
                        'cost' => $cost,
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
}
