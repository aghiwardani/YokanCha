<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::orderBy('name')->get();
        $periods = PoPeriod::orderByDesc('created_at')->get();

        return view('admin.products.index', [
            'products' => $products,
            'periods' => $periods,
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);

        Product::create($validated);

        return redirect()->route('admin.products')->with('status', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->validateProduct($request);

        $product->update($validated);

        return redirect()->route('admin.products')->with('status', 'Produk berhasil diubah.');
    }

    public function setQuota(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'po_period_id' => ['required', 'exists:po_periods,id'],
            'quota' => ['required', 'integer', 'min:0'],
        ]);

        PoPeriodProduct::updateOrCreate(
            [
                'po_period_id' => $validated['po_period_id'],
                'product_id' => $product->id,
            ],
            [
                'quota' => $validated['quota'],
            ]
        );

        return redirect()->route('admin.products')->with('status', 'Kuota berhasil disimpan.');
    }

    private function validateProduct(Request $request): array
    {
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
