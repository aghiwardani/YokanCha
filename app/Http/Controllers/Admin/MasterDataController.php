<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\OrderItem;
use App\Models\OrderItemIngredient;
use App\Models\PoPeriodIngredient;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\Size;
use Illuminate\View\View;

/**
 * One admin page for the master lists: products (Produk), ingredients (Bahan)
 * and sizes (Ukuran). The product form itself stays on its own page. Besides
 * the lists, it works out how each row is used so the page can say why
 * something can't be deleted, or which recipe is still missing.
 */
class MasterDataController extends Controller
{
    public function index(): View
    {
        $products = Product::with('recipes')->orderBy('sort_order')->orderBy('name')->get();
        $ingredients = Ingredient::orderBy('name')->get();
        $sizes = Size::smallestFirst()->get();

        $orderedProductIds = OrderItem::distinct()->pluck('product_id')->flip();

        $productInfo = $products->mapWithKeys(function (Product $product) use ($orderedProductIds) {
            $sizes = $product->sizesSmallestFirst();
            $withRecipe = $product->recipes->pluck('size')->unique()->all();
            $needsRecipe = $sizes === [] ? ['-'] : $sizes;

            return [$product->id => [
                'sizes' => $sizes,
                'missing_recipe' => array_values(array_diff($needsRecipe, $withRecipe)),
                'ordered' => $orderedProductIds->has($product->id),
            ]];
        });

        $recipeProducts = ProductRecipe::selectRaw('ingredient_id, count(distinct product_id) as total')
            ->groupBy('ingredient_id')->pluck('total', 'ingredient_id');
        $stockPeriods = PoPeriodIngredient::selectRaw('ingredient_id, count(*) as total')
            ->groupBy('ingredient_id')->pluck('total', 'ingredient_id');
        $orderedIngredients = OrderItemIngredient::distinct()->pluck('ingredient_id')->flip();

        $ingredientInfo = $ingredients->mapWithKeys(fn (Ingredient $ingredient) => [$ingredient->id => [
            'products' => (int) ($recipeProducts[$ingredient->id] ?? 0),
            'periods' => (int) ($stockPeriods[$ingredient->id] ?? 0),
            'ordered' => $orderedIngredients->has($ingredient->id),
        ]]);

        $sizeInfo = $sizes->mapWithKeys(fn (Size $size) => [$size->id => [
            'products' => $products->filter(fn (Product $product) => in_array($size->name, $product->size_options ?? [], true))->count(),
        ]]);

        return view('admin.master.index', [
            'products' => $products,
            'ingredients' => $ingredients,
            'sizes' => $sizes,
            'productInfo' => $productInfo,
            'ingredientInfo' => $ingredientInfo,
            'sizeInfo' => $sizeInfo,
        ]);
    }
}
