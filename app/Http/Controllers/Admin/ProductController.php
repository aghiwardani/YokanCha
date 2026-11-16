<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Product;
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
        return view('admin.products.form', [
            'product' => new Product(),
            'ingredients' => Ingredient::orderBy('name')->get(),
            'recipes' => [],
        ]);
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
        $recipes = [];
        foreach ($product->recipes as $row) {
            $recipes[$row->size][$row->ingredient_id] = $row;
        }

        return view('admin.products.form', [
            'product' => $product,
            'ingredients' => Ingredient::orderBy('name')->get(),
            'recipes' => $recipes,
        ]);
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
            'size_options' => ['nullable', 'string'],
            'sweetness_options' => ['nullable', 'string'],
            'tag' => ['nullable', 'string', 'max:40'],
            'tone' => ['nullable', 'in:'.implode(',', Product::TONES)],
            'bottle_style' => ['nullable', 'in:'.implode(',', Product::BOTTLE_STYLES)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'recipe' => ['nullable', 'array', $amountsRule],
            'recipe_less_sugar' => ['nullable', 'array', $amountsRule],
        ]);

        [$sizes, $sizePrices] = $this->parseSizes($validated['size_options'] ?? null);

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
     * Parse "250 ml=32000, 1 liter=110000" into size names and a size => price
     * map. Entries without "=price" are kept as sizes priced at base_price.
     *
     * @return array{0: array<int, string>, 1: array<string, int>}
     */
    private function parseSizes(?string $value): array
    {
        $sizes = [];
        $prices = [];

        foreach ($this->splitOptions($value) as $entry) {
            $parts = array_map('trim', explode('=', $entry, 2));
            $name = $parts[0];

            if ($name === '') {
                continue;
            }

            $sizes[] = $name;

            if (isset($parts[1]) && ctype_digit($digits = str_replace(['.', ' '], '', $parts[1]))) {
                $prices[$name] = (int) $digits;
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
