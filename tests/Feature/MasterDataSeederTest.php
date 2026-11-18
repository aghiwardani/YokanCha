<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\Size;
use App\Models\SweetnessLevel;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_owner_master_data(): void
    {
        $this->seed(MasterDataSeeder::class);

        $this->assertSame(['250 ml', '500 ml', '1 Liter'], Size::smallestFirst()->pluck('name')->all());
        $this->assertSame(['No Sugar', 'Less Sugar', 'Normal', 'Extra Sugar'], SweetnessLevel::leastSweetFirst()->pluck('name')->all());
        $this->assertSame(8, Ingredient::count());
        $this->assertTrue(Ingredient::where('name', 'Oatside')->exists());
        $this->assertFalse(Ingredient::where('name', 'Outside')->exists());
        $this->assertSame(['Maple Syrup', 'Simple Syrup'], Ingredient::where('is_sugar', true)->orderBy('name')->pluck('name')->all());
        $this->assertSame(5, Product::count());

        $maple = Product::where('name', 'Maple Signature')->firstOrFail();
        $this->assertSame(54000, $maple->priceFor('500 ml'));
        $this->assertSame(['250 ml', '500 ml', '1 Liter'], $maple->size_options);
        $this->assertEquals(2.1, $maple->recipes()->where('size', '250 ml')
            ->where('ingredient_id', Ingredient::where('name', 'Matcha Longji')->value('id'))->value('amount'));

        // Every product has a recipe for every size it sells.
        foreach (Product::all() as $product) {
            foreach ($product->size_options as $size) {
                $this->assertTrue($product->recipes()->where('size', $size)->exists(), "{$product->name} {$size} has no recipe");
            }
        }
    }

    public function test_running_it_twice_does_not_duplicate_anything(): void
    {
        $this->seed(MasterDataSeeder::class);
        $recipes = ProductRecipe::count();

        $this->seed(MasterDataSeeder::class);

        $this->assertSame(5, Product::count());
        $this->assertSame(8, Ingredient::count());
        $this->assertSame(3, Size::count());
        $this->assertSame(4, SweetnessLevel::count());
        $this->assertSame($recipes, ProductRecipe::count());
    }

    public function test_database_seeder_gives_admin_and_master_data_only(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertTrue(User::first()->is_admin);
        $this->assertSame(5, Product::count());
        $this->assertDatabaseCount('po_periods', 0);
        $this->assertDatabaseCount('orders', 0);
    }
}
