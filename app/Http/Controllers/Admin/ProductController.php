<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::orderBy('name')->get();

        return view('admin.products.index', [
            'products' => $products,
        ]);
    }

    public function create(): View
    {
        return $this->form(new Product());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);

        DB::transaction(function () use ($request, $validated) {
            $product = Product::create($validated);
            $this->syncRecipes($request, $product);
        });

        return redirect()->route('admin.products')->with('status', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product): View
    {
        return $this->form($product);
    }

    private function form(Product $product): View
    {
        $recipes = [];
        foreach ($product->recipes as $row) {
            $recipes[$row->size][$row->ingredient_id] = $row;
        }

        $sizes = Size::smallestFirst()->get();

        return view('admin.products.form', [
            'product' => $product,
            'ingredients' => Ingredient::orderBy('name')->get(),
            'sizes' => $sizes,
            'recipes' => $recipes,
            'recipeDefaults' => $this->scaledRecipeDefaults($recipes, $sizes),
        ]);
    }

    /**
     * Suggested recipe for every master size that has no saved recipe yet:
     * the smallest saved size's amounts scaled by volume (250 ml → 500 ml
     * doubles each amount). Only shown in the form; saved when submitted.
     *
     * @param  array<string, array<int, \App\Models\ProductRecipe>>  $recipes
     * @return array<string, array{amounts: array<int, float>, less_sugar: float|null}>
     */
    private function scaledRecipeDefaults(array $recipes, $sizes): array
    {
        $base = $sizes->first(fn (Size $size) => $size->volume_ml > 0 && isset($recipes[$size->name]));

        if (! $base) {
            return [];
        }

        $defaults = [];

        foreach ($sizes as $size) {
            if (isset($recipes[$size->name]) || $size->volume_ml <= 0) {
                continue;
            }

            $factor = $size->volume_ml / $base->volume_ml;
            $amounts = [];
            $lessSugar = null;

            foreach ($recipes[$base->name] as $ingredientId => $row) {
                $amounts[$ingredientId] = round((float) $row->amount * $factor, 2);

                if ($row->amount_less_sugar !== null) {
                    $lessSugar = round((float) $row->amount_less_sugar * $factor, 2);
                }
            }

            $defaults[$size->name] = ['amounts' => $amounts, 'less_sugar' => $lessSugar];
        }

        return $defaults;
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->validateProduct($request);

        DB::transaction(function () use ($request, $validated, $product) {
            $product->update($validated);
            $this->syncRecipes($request, $product);
        });

        return redirect()->route('admin.products')->with('status', 'Produk berhasil diubah.');
    }

    private function validateProduct(Request $request): array
    {
        // Size names may contain dots ("1.5 liter"), which break wildcard rules,
        // so every leaf amount is checked here instead of with recipe.*.*.
        $amountsRule = function (string $attribute, mixed $value, \Closure $fail) {
            if (! is_array($value)) {
                $fail('Takaran resep tidak valid.');

                return;
            }

            $leaves = [];
            array_walk_recursive($value, function ($amount) use (&$leaves) {
                $leaves[] = $amount;
            });

            foreach ($leaves as $amount) {
                if ($amount !== null && $amount !== '' && (! is_numeric($amount) || $amount < 0 || $amount > 99999999)) {
                    $fail('Takaran resep harus berupa angka antara 0 dan 99999999.');

                    return;
                }
            }
        };

        $validated = $request->validate([
            'category' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'integer', 'min:0'],
            'sizes' => ['nullable', 'array', function (string $attribute, mixed $value, \Closure $fail) {
                $ids = array_map('intval', array_keys((array) $value));
                if (Size::whereIn('id', $ids)->count() !== count(array_unique($ids))) {
                    $fail('Ada ukuran yang tidak dikenal. Muat ulang halaman, lalu coba lagi.');
                }
            }],
            'sizes.*.on' => ['nullable'],
            'sizes.*.price' => ['nullable', 'integer', 'min:0'],
            'sweetness_options' => ['nullable', 'string'],
            'tag' => ['nullable', 'string', 'max:40'],
            'tone' => ['nullable', 'in:'.implode(',', Product::TONES)],
            'bottle_style' => ['nullable', 'in:'.implode(',', Product::BOTTLE_STYLES)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'recipe' => ['nullable', 'array', $amountsRule],
            'recipe_less_sugar' => ['nullable', 'array', $amountsRule],
        ]);

        [$sizes, $sizePrices] = $this->tickedSizes($validated['sizes'] ?? []);

        return [
            'category' => $validated['category'] ?? 'minuman',
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'base_price' => $validated['base_price'],
            'size_options' => $sizes,
            'size_prices' => $sizePrices ?: null,
            'sweetness_options' => $this->splitOptions($validated['sweetness_options'] ?? null),
            'tag' => $validated['tag'] ?? null,
            'tone' => $validated['tone'] ?? null,
            'bottle_style' => $validated['bottle_style'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }

    /**
     * Replace the product's recipe rows with the submitted grid. Only amounts
     * above zero are kept, only for the saved sizes (or '-' without sizes),
     * and amount_less_sugar lives on the sugar ingredient's row alone.
     */
    private function syncRecipes(Request $request, Product $product): void
    {
        $product->recipes()->delete();

        $sizes = $product->size_options ?: ['-'];
        $recipe = (array) $request->input('recipe', []);
        $lessSugar = (array) $request->input('recipe_less_sugar', []);
        $ingredients = Ingredient::all()->keyBy('id');

        foreach ($sizes as $size) {
            foreach ((array) ($recipe[$size] ?? []) as $ingredientId => $amount) {
                $ingredient = $ingredients->get($ingredientId);

                if (! $ingredient || ! is_numeric($amount) || (float) $amount <= 0) {
                    continue;
                }

                $less = $lessSugar[$size] ?? null;

                $product->recipes()->create([
                    'size' => $size,
                    'ingredient_id' => $ingredient->id,
                    'amount' => $amount,
                    'amount_less_sugar' => $ingredient->is_sugar && is_numeric($less) && (float) $less > 0 ? $less : null,
                ]);
            }
        }
    }

    /**
     * Turn the size checkboxes (sizes[<size id>][on|price]) into size names,
     * smallest first, and a size => price map. A ticked size without a price
     * is sold at base_price; a price on an unticked size is ignored.
     *
     * @return array{0: array<int, string>, 1: array<string, int>}
     */
    private function tickedSizes(array $input): array
    {
        $ticked = array_filter($input, fn ($row) => ! empty($row['on']));

        $sizes = [];
        $prices = [];

        foreach (Size::smallestFirst()->whereIn('id', array_keys($ticked))->get() as $size) {
            $sizes[] = $size->name;

            $price = $ticked[$size->id]['price'] ?? null;
            if ($price !== null && $price !== '') {
                $prices[$size->name] = (int) $price;
            }
        }

        return [$sizes, $prices];
    }

    private function splitOptions(?string $value): array
    {
        if (! $value) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($item) => $item !== ''));
    }
}
