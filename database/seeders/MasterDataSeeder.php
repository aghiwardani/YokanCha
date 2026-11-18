<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\Size;
use App\Models\SweetnessLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * YokanCha's real master data: sizes, sweetness levels, ingredients and the
 * products with their prices, sweetness choices and per-size recipes (Normal
 * sweetness amounts). Exported from the owner's local database on 2026-11-18.
 *
 * Runs as part of `php artisan migrate:fresh --seed` (via DatabaseSeeder) or
 * on its own:
 *
 *   php artisan db:seed --class=MasterDataSeeder
 *
 * Safe to re-run: rows are matched by name, so running it again resets these
 * products, prices and recipes to the values below (edits made in the admin
 * to these same rows are overwritten). It never touches periods, stock or
 * orders.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $sizes = [
            '250 ml' => 250,
            '500 ml' => 500,
            '1 Liter' => 1000,
        ];

        $sweetnessLevels = [
            'No Sugar' => 0,
            'Less Sugar' => 50,
            'Normal' => 100,
            'Extra Sugar' => 150,
        ];

        // name => [unit, is sugar]
        $ingredients = [
            'Air' => ['ml', false],
            'cashew milk' => ['ml', false],
            'Fresh Milk' => ['ml', false],
            'Maple Syrup' => ['ml', true],
            'Matcha Longji' => ['g', false],
            'Matcha Tokyo' => ['g', false],
            'Outside' => ['ml', false],
            'Simple Syrup' => ['ml', true],
        ];

        $products = [
            [
                'name' => 'Maple Signature',
                'description' => 'Maple Signature (Creamy, Nutty & Warm Maple)',
                'tag' => 'Maple Signature',
                'tone' => 'sage',
                'bottle_style' => 'maple',
                'base_price' => 28000,
                'sort_order' => 1,
                'is_active' => true,
                // size => price (null = base price)
                'sizes' => ['250 ml' => 28000, '500 ml' => 54000, '1 Liter' => 105000],
                'sweetness' => ['No Sugar', 'Less Sugar', 'Normal', 'Extra Sugar'],
                // size => [ingredient => Normal amount]
                'recipe' => [
                    '250 ml' => ['Air' => 30, 'Fresh Milk' => 100, 'Maple Syrup' => 15, 'Matcha Longji' => 2.1, 'Matcha Tokyo' => 1.4, 'Outside' => 70, 'cashew milk' => 30],
                    '500 ml' => ['Air' => 60, 'Fresh Milk' => 200, 'Maple Syrup' => 30, 'Matcha Longji' => 4.2, 'Matcha Tokyo' => 2.8, 'Outside' => 140, 'cashew milk' => 60],
                    '1 Liter' => ['Air' => 120, 'Fresh Milk' => 400, 'Maple Syrup' => 60, 'Matcha Longji' => 8.4, 'Matcha Tokyo' => 5.6, 'Outside' => 280, 'cashew milk' => 120],
                ],
            ],
            [
                'name' => 'Classic Artisan',
                'description' => 'Classic Artisan (Rich Umami)',
                'tag' => 'Classic Artisan',
                'tone' => 'sage',
                'bottle_style' => 'oat',
                'base_price' => 25000,
                'sort_order' => 2,
                'is_active' => true,
                // size => price (null = base price)
                'sizes' => ['250 ml' => 25000, '500 ml' => 48000, '1 Liter' => 95000],
                'sweetness' => ['No Sugar', 'Less Sugar', 'Normal', 'Extra Sugar'],
                // size => [ingredient => Normal amount]
                'recipe' => [
                    '250 ml' => ['Air' => 30, 'Fresh Milk' => 103.75, 'Matcha Longji' => 2.1, 'Matcha Tokyo' => 1.4, 'Outside' => 72.5, 'Simple Syrup' => 15, 'cashew milk' => 31.25],
                    '500 ml' => ['Air' => 60, 'Fresh Milk' => 207.5, 'Matcha Longji' => 4.2, 'Matcha Tokyo' => 2.8, 'Outside' => 145, 'Simple Syrup' => 30, 'cashew milk' => 62.5],
                    '1 Liter' => ['Air' => 120, 'Fresh Milk' => 415, 'Matcha Longji' => 8.4, 'Matcha Tokyo' => 5.6, 'Outside' => 290, 'Simple Syrup' => 60, 'cashew milk' => 125],
                ],
            ],
            [
                'name' => 'Oat-Cashew Nutty Maple',
                'description' => 'Oat-Cashew Nutty Maple (100% Plant-Based / Dairy-Free)',
                'tag' => 'Oat-Cashew Nutty Maple',
                'tone' => 'oat',
                'bottle_style' => 'maple',
                'base_price' => 30000,
                'sort_order' => 3,
                'is_active' => true,
                // size => price (null = base price)
                'sizes' => ['250 ml' => 30000, '500 ml' => 58000, '1 Liter' => 112000],
                'sweetness' => ['No Sugar', 'Less Sugar', 'Normal', 'Extra Sugar'],
                // size => [ingredient => Normal amount]
                'recipe' => [
                    '250 ml' => ['Air' => 30, 'Maple Syrup' => 12, 'Matcha Longji' => 2.1, 'Matcha Tokyo' => 1.4, 'Outside' => 105, 'cashew milk' => 100],
                    '500 ml' => ['Air' => 60, 'Maple Syrup' => 24, 'Matcha Longji' => 4.2, 'Matcha Tokyo' => 2.8, 'Outside' => 210, 'cashew milk' => 200],
                    '1 Liter' => ['Air' => 120, 'Maple Syrup' => 48, 'Matcha Longji' => 8.4, 'Matcha Tokyo' => 5.6, 'Outside' => 420, 'cashew milk' => 400],
                ],
            ],
            [
                'name' => 'Pure Cashew Velvet Matcha',
                'description' => 'Pure Cashew Velvet Matcha (Ultra-Creamy & Low-Calorie)',
                'tag' => 'Pure Cashew Velvet Matcha',
                'tone' => 'amber',
                'bottle_style' => 'klasik',
                'base_price' => 28000,
                'sort_order' => 4,
                'is_active' => true,
                // size => price (null = base price)
                'sizes' => ['250 ml' => 28000, '500 ml' => 54000, '1 Liter' => 105000],
                'sweetness' => ['No Sugar', 'Less Sugar', 'Normal', 'Extra Sugar'],
                // size => [ingredient => Normal amount]
                'recipe' => [
                    '250 ml' => ['Air' => 30, 'Matcha Longji' => 2.1, 'Matcha Tokyo' => 1.4, 'Simple Syrup' => 10, 'cashew milk' => 205],
                    '500 ml' => ['Air' => 60, 'Matcha Longji' => 4.2, 'Matcha Tokyo' => 2.8, 'Simple Syrup' => 20, 'cashew milk' => 410],
                    '1 Liter' => ['Air' => 120, 'Matcha Longji' => 8.4, 'Matcha Tokyo' => 5.6, 'Simple Syrup' => 40, 'cashew milk' => 820],
                ],
            ],
            [
                'name' => 'Clear Matcha',
                'description' => 'Clear Matcha (Zero Milk / Pure Tea)',
                'tag' => 'Clear Matcha',
                'tone' => 'sage',
                'bottle_style' => 'murni',
                'base_price' => 20000,
                'sort_order' => 5,
                'is_active' => true,
                // size => price (null = base price)
                'sizes' => ['250 ml' => 20000, '500 ml' => 38000, '1 Liter' => 75000],
                'sweetness' => ['No Sugar', 'Less Sugar', 'Normal', 'Extra Sugar'],
                // size => [ingredient => Normal amount]
                'recipe' => [
                    '250 ml' => ['Air' => 235, 'Matcha Longji' => 1, 'Matcha Tokyo' => 2.5, 'Simple Syrup' => 10],
                    '500 ml' => ['Air' => 470, 'Matcha Longji' => 2, 'Matcha Tokyo' => 5, 'Simple Syrup' => 20],
                    '1 Liter' => ['Air' => 940, 'Matcha Longji' => 4, 'Matcha Tokyo' => 10, 'Simple Syrup' => 40],
                ],
            ],
        ];

        DB::transaction(function () use ($sizes, $sweetnessLevels, $ingredients, $products) {
            foreach ($sizes as $name => $volume) {
                Size::updateOrCreate(['name' => $name], ['volume_ml' => $volume]);
            }

            foreach ($sweetnessLevels as $name => $percent) {
                SweetnessLevel::updateOrCreate(['name' => $name], ['percent' => $percent]);
            }

            $ingredientIds = [];
            foreach ($ingredients as $name => [$unit, $isSugar]) {
                $ingredientIds[$name] = Ingredient::updateOrCreate(
                    ['name' => $name],
                    ['unit' => $unit, 'is_sugar' => $isSugar],
                )->id;
            }

            foreach ($products as $data) {
                $prices = array_filter($data['sizes'], fn ($price) => $price !== null);

                $product = Product::updateOrCreate(
                    ['name' => $data['name']],
                    [
                        'category' => 'minuman',
                        'description' => $data['description'],
                        'base_price' => $data['base_price'],
                        'size_options' => array_keys($data['sizes']),
                        'size_prices' => $prices ?: null,
                        'sweetness_options' => $data['sweetness'],
                        'tag' => $data['tag'],
                        'tone' => $data['tone'],
                        'bottle_style' => $data['bottle_style'],
                        'sort_order' => $data['sort_order'],
                        'is_active' => $data['is_active'],
                    ],
                );

                $product->recipes()->delete();
                foreach ($data['recipe'] as $size => $amounts) {
                    foreach ($amounts as $ingredient => $amount) {
                        ProductRecipe::create([
                            'product_id' => $product->id,
                            'size' => $size,
                            'ingredient_id' => $ingredientIds[$ingredient],
                            'amount' => $amount,
                        ]);
                    }
                }
            }
        });
    }
}
