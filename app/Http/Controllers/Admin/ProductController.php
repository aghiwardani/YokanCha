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
        ]);

        return [
            'category' => $validated['category'] ?? 'minuman',
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'base_price' => $validated['base_price'],
            'size_options' => $this->splitOptions($validated['size_options'] ?? null),
            'sweetness_options' => $this->splitOptions($validated['sweetness_options'] ?? null),
        ];
    }

    private function splitOptions(?string $value): array
    {
        if (! $value) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($item) => $item !== ''));
    }
}
