<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Buku resep": every product's recipe per size as a PDF download, with the
 * sugar amount worked out per sweetness level.
 */
class RecipeExportController extends Controller
{
    public function __invoke(): Response
    {
        $pdf = Pdf::loadView('admin.recipes.pdf', $this->book())->setPaper('a4');

        return $pdf->download('resep-yokancha-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * Data for the recipe book view: products (active first), their recipe
     * per size, sugar rows and the sweetness levels they offer.
     */
    public function book(): array
    {
        $products = Product::with('recipes.ingredient')
            ->orderByDesc('is_active')->orderBy('sort_order')->orderBy('name')
            ->get()
            ->map(function (Product $product) {
                $sizes = $product->sizesSmallestFirst() ?: ['-'];
                $recipes = $product->recipes->filter(fn ($r) => $r->ingredient);

                // One row per ingredient, one column per size.
                $rows = $recipes->groupBy('ingredient_id')->map(fn ($group) => [
                    'name' => $group->first()->ingredient->name,
                    'unit' => $group->first()->ingredient->unit,
                    'sugar' => (bool) $group->first()->ingredient->is_sugar,
                    'amounts' => collect($sizes)->mapWithKeys(fn ($size) => [
                        $size => optional($group->firstWhere('size', $size))->amount,
                    ])->all(),
                ])->sortBy([['sugar', 'asc'], ['name', 'asc']])->values()->all();

                // Sweetness levels this product offers, least sweet first.
                $offered = $product->sweetnessLeastFirst();

                return [
                    'product' => $product,
                    'sizes' => $sizes,
                    'rows' => $rows,
                    'sugarRows' => array_values(array_filter($rows, fn ($r) => $r['sugar'])),
                    'levels' => $offered ?: [],
                    'missing' => array_values(array_filter($sizes, fn ($size) => $size !== '-'
                        && ! $recipes->contains('size', $size))),
                ];
            });

        $logoPath = public_path('icon-192.png');
        $logo = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;

        return [
            'products' => $products,
            'logo' => $logo,
            'printedAt' => now()->timezone(config('app.timezone')),
        ];
    }
}
