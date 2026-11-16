<?php

namespace Tests\Concerns;

use App\Models\Ingredient;
use App\Models\PoPeriod;
use App\Models\PoPeriodIngredient;
use App\Models\Product;
use App\Models\ProductRecipe;

trait StocksMenu
{
    protected function ingredient(string $name, string $unit = 'ml', bool $isSugar = false): Ingredient
    {
        return Ingredient::create(['name' => $name, 'unit' => $unit, 'is_sugar' => $isSugar]);
    }

    protected function stock(PoPeriod $period, Ingredient $ingredient, float $stock, float $used = 0): PoPeriodIngredient
    {
        return PoPeriodIngredient::create([
            'po_period_id' => $period->id,
            'ingredient_id' => $ingredient->id,
            'stock' => $stock,
            'used' => $used,
        ]);
    }

    protected function recipe(Product $product, string $size, Ingredient $ingredient, float $amount, ?float $lessSugar = null): ProductRecipe
    {
        return ProductRecipe::create([
            'product_id' => $product->id,
            'size' => $size,
            'ingredient_id' => $ingredient->id,
            'amount' => $amount,
            'amount_less_sugar' => $lessSugar,
        ]);
    }
}
