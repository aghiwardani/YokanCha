<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Starting data for a fresh database (`php artisan migrate:fresh --seed`):
     * the admin account and YokanCha's master data (sizes, sweetness levels,
     * ingredients, products with prices and recipes). Periods, stock and
     * orders start empty. The demo menu (MenuDemoSeeder) is not included.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);
        $this->call(MasterDataSeeder::class);
    }
}
