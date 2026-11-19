<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\PoPeriod;
use App\Models\PoPeriodIngredient;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\Size;
use App\Models\SweetnessLevel;
use Illuminate\Database\Seeder;

/**
 * Seeds the five menu items shown in the Figma mockup ("Menu batch ini") and
 * adds each to the currently open PO period (creating one if none is open).
 * Also seeds the ingredient list, stocks it in that period, and gives each menu
 * a per-size recipe so the menu is orderable right after seeding.
 * Only for demo/empty databases: it overwrites the descriptions and prices of
 * these five products and adds demo stock, so do not run it on live data.
 * Safe to re-run: products/ingredients/recipes are upserted; existing period
 * stock rows are left untouched so `used` (and any admin-edited stock) is kept.
 *
 *   php artisan db:seed --class=MenuDemoSeeder
 */
class MenuDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Sugar for each level = recipe's Normal amount x percent.
        $sweetnessLevels = ['Tanpa gula' => 0, 'Less sugar' => 50, 'Manis normal' => 100, 'Extra manis' => 150];
        $sweetness = ['Less sugar', 'Manis normal', 'Extra manis'];

        $products = [
            [
                'name' => 'Maple Matcha Latte',
                'description' => 'Matcha dual-blend, Signature Milk, dan sirup maple di dasar botol. Kocok dulu biar manis karamelnya rata.',
                'size_prices' => ['250 ml' => 32000, '1 liter' => 110000],
                'tag' => 'Maple Signature',
                'tone' => 'amber',
                'bottle_style' => 'maple',
            ],
            [
                'name' => 'Matcha Latte Klasik',
                'description' => 'Matcha dan susu dengan manis yang pas. Cocok buat yang baru kenalan sama matcha.',
                'size_prices' => ['250 ml' => 28000, '1 liter' => 95000],
                'tag' => null,
                'tone' => 'sage',
                'bottle_style' => 'klasik',
            ],
            [
                'name' => 'Matcha Oat',
                'description' => 'Pakai oat milk, bukan susu sapi. Teksturnya creamy, ada sedikit rasa gurih.',
                'size_prices' => ['250 ml' => 32000, '1 liter' => 108000],
                'tag' => null,
                'tone' => 'oat',
                'bottle_style' => 'oat',
            ],
            [
                'name' => 'Matcha Murni',
                'description' => 'Cuma matcha dan air dingin, tanpa susu. Pahitnya lembut dan segar.',
                'size_prices' => ['250 ml' => 26000, '1 liter' => 88000],
                'tag' => 'Stok terbatas',
                'tone' => 'sage',
                'bottle_style' => 'murni',
            ],
            [
                'name' => 'Strawberry Matcha',
                'description' => 'Puree stroberi di bawah, matcha di atas. Mau dikocok atau diminum berlapis, dua-duanya enak.',
                'size_prices' => ['250 ml' => 34000, '1 liter' => 118000],
                'tag' => null,
                'tone' => 'amber',
                'bottle_style' => 'strawberry',
            ],
        ];

        $period = PoPeriod::currentlyOpen() ?? PoPeriod::create([
            'name' => 'Batch Release 2026',
            'opens_at' => now()->subHour(),
            'closes_at' => null,
            'status' => 'open',
        ]);

        $ingredientDefs = [
            'Matcha Longji' => ['g', 100, false],
            'Matcha Tokyo' => ['g', 100, false],
            'Fresh Milk' => ['ml', 2000, false],
            'Oatside' => ['ml', 2000, false],
            'Cashew Milk' => ['ml', 2000, false],
            'Gula cair' => ['ml', 500, true],
        ];
        foreach (['250 ml' => 250, '500 ml' => 500, '1 liter' => 1000] as $name => $volume) {
            Size::firstOrCreate(['name' => $name], ['volume_ml' => $volume]);
        }
        foreach ($sweetnessLevels as $name => $percent) {
            SweetnessLevel::firstOrCreate(['name' => $name], ['percent' => $percent]);
        }

        $ingredients = [];
        foreach ($ingredientDefs as $name => [$unit, $stock, $isSugar]) {
            $ingredients[$name] = Ingredient::updateOrCreate(
                ['name' => $name],
                ['unit' => $unit, 'is_sugar' => $isSugar],
            );
            // Create only: never reset `used` or overwrite stock on re-run.
            PoPeriodIngredient::firstOrCreate(
                ['po_period_id' => $period->id, 'ingredient_id' => $ingredients[$name]->id],
                ['stock' => $stock, 'used' => 0],
            );
        }

        // product name => [matcha, milk (null = none), sugar (false = none)]
        $recipes = [
            'Maple Matcha Latte' => ['Matcha Longji', 'Fresh Milk', true],
            'Matcha Latte Klasik' => ['Matcha Tokyo', 'Fresh Milk', true],
            'Matcha Oat' => ['Matcha Tokyo', 'Oatside', true],
            'Matcha Murni' => ['Matcha Longji', null, false],
            'Strawberry Matcha' => ['Matcha Tokyo', 'Fresh Milk', true],
        ];

        foreach ($products as $index => $data) {
            $product = Product::updateOrCreate(
                ['name' => $data['name']],
                [
                    'category' => 'minuman',
                    'description' => $data['description'],
                    'base_price' => reset($data['size_prices']),
                    'size_options' => array_keys($data['size_prices']),
                    'size_prices' => $data['size_prices'],
                    // Matcha Murni has no sugar, so no sweetness choice.
                    'sweetness_options' => $recipes[$data['name']][2] ? $sweetness : [],
                    'tag' => $data['tag'],
                    'tone' => $data['tone'],
                    'bottle_style' => $data['bottle_style'],
                    'sort_order' => $index + 1,
                ],
            );

            PoPeriodProduct::updateOrCreate(
                ['po_period_id' => $period->id, 'product_id' => $product->id],
            );

            [$matcha, $milk, $sugar] = $recipes[$data['name']];
            $rows = [[$matcha, 4]];
            if ($milk) {
                $rows[] = [$milk, 200];
            }
            if ($sugar) {
                $rows[] = ['Gula cair', 15];
            }
            // 250 ml amounts (Normal sweetness); 1 liter is 4x.
            foreach (['250 ml' => 1, '1 liter' => 4] as $size => $factor) {
                foreach ($rows as [$ingredientName, $amount]) {
                    ProductRecipe::updateOrCreate(
                        ['product_id' => $product->id, 'size' => $size, 'ingredient_id' => $ingredients[$ingredientName]->id],
                        ['amount' => $amount * $factor],
                    );
                }
            }
        }
    }
}
