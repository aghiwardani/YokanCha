<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\PoPeriod;
use App\Models\PoPeriodIngredient;
use App\Models\PoPeriodProduct;
use App\Models\Product;
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
        foreach ($ingredients as $ingredient) {
            $row = $rows->get($ingredient->id);
            $values[$ingredient->id] = $row ? $row->stock : null;
        }

        $prefilled = $request->query('prefill') === 'previous';
        if ($prefilled) {
            $previous = PoPeriod::whereKeyNot($period->getKey())
                ->orderByDesc('created_at')->orderByDesc('id')->first();

            if ($previous) {
                foreach ($previous->ingredientStocks()->get() as $prev) {
                    if (array_key_exists($prev->ingredient_id, $values)) {
                        $values[$prev->ingredient_id] = max(0, $prev->remaining());
                    }
                }
            }
        }

        return view('admin.periods.stock', [
            'period' => $period,
            'ingredients' => $ingredients,
            'rows' => $rows,
            'values' => $values,
            'prefilled' => $prefilled,
            'products' => Product::orderBy('sort_order')->orderBy('name')->get(),
            'selectedProducts' => $period->poPeriodProducts()->pluck('product_id')->all(),
        ]);
    }

    public function updateStock(Request $request, PoPeriod $period): RedirectResponse
    {
        $request->validate([
            'stock' => ['nullable', 'array'],
            'stock.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'products' => ['nullable', 'array'],
            'products.*' => ['integer', 'exists:products,id'],
        ]);

        $input = $request->input('stock', []);

        DB::transaction(function () use ($request, $period, $input) {
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

                if ($row) {
                    $row->update(['stock' => $raw]);
                } else {
                    PoPeriodIngredient::create([
                        'po_period_id' => $period->id,
                        'ingredient_id' => $ingredient->id,
                        'stock' => $raw,
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
}
