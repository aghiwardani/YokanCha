<?php

namespace Database\Seeders;

use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Seeds the five menu items shown in the Figma mockup ("Menu batch ini") and
 * gives each a quota in the currently open PO period (creating one if none is
 * open). Safe to re-run: products are matched by name, quotas are upserted
 * without touching ordered_qty.
 *
 *   php artisan db:seed --class=MenuDemoSeeder
 */
class MenuDemoSeeder extends Seeder
{
    public function run(): void
    {
        $sweetness = ['Manis normal', 'Less sugar'];

        $products = [
            [
                'name' => 'Maple Matcha Latte',
                'description' => 'Matcha dual-blend, susu D.O.A, dan sirup maple di dasar botol. Kocok dulu biar manis karamelnya rata.',
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

        foreach ($products as $index => $data) {
            $product = Product::updateOrCreate(
                ['name' => $data['name']],
                [
                    'category' => 'minuman',
                    'description' => $data['description'],
                    'base_price' => reset($data['size_prices']),
                    'size_options' => array_keys($data['size_prices']),
                    'size_prices' => $data['size_prices'],
                    'sweetness_options' => $sweetness,
                    'tag' => $data['tag'],
                    'tone' => $data['tone'],
                    'bottle_style' => $data['bottle_style'],
                    'sort_order' => $index + 1,
                ],
            );

            PoPeriodProduct::updateOrCreate(
                ['po_period_id' => $period->id, 'product_id' => $product->id],
                ['quota' => 30],
            );
        }
    }
}
