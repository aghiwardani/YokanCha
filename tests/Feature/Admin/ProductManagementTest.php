<?php

namespace Tests\Feature\Admin;

use App\Models\PoPeriod;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StocksMenu;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;
    use StocksMenu;

    public function test_admin_can_create_product(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->post('/admin/products', [
            'name' => 'Es Teh Lemon',
            'description' => 'Teh dingin dengan lemon segar',
            'base_price' => 15000,
            'size_options' => '250ml, 1L',
            'sweetness_options' => 'Normal, Kurang Gula',
        ]);

        $response->assertRedirect('/admin/products');

        $this->assertDatabaseHas('products', [
            'name' => 'Es Teh Lemon',
            'description' => 'Teh dingin dengan lemon segar',
            'base_price' => 15000,
        ]);

        $product = Product::where('name', 'Es Teh Lemon')->first();
        $this->assertSame(['250ml', '1L'], $product->size_options);
        $this->assertSame(['Normal', 'Kurang Gula'], $product->sweetness_options);
    }

    public function test_admin_can_set_price_per_size_and_display_fields(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post('/admin/products', [
            'name' => 'Maple Matcha Latte',
            'base_price' => 32000,
            'size_options' => '250 ml=32000, 1 liter=110000',
            'sweetness_options' => 'Manis normal, Less sugar',
            'tag' => 'Maple Signature',
            'tone' => 'amber',
            'bottle_style' => 'maple',
            'sort_order' => 1,
        ])->assertRedirect('/admin/products');

        $product = Product::where('name', 'Maple Matcha Latte')->first();
        $this->assertSame(['250 ml', '1 liter'], $product->size_options);
        $this->assertSame(['250 ml' => 32000, '1 liter' => 110000], $product->size_prices);
        $this->assertSame(110000, $product->priceFor('1 liter'));
        $this->assertSame('Maple Signature', $product->tag);
        $this->assertSame('amber', $product->tone);
        $this->assertSame('maple', $product->bottle_style);
        $this->assertSame(1, $product->sort_order);
    }

    public function test_quota_route_is_gone(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $product = Product::factory()->create();
        $period = PoPeriod::factory()->create();

        $response = $this->post("/admin/products/{$product->id}/quota", [
            'po_period_id' => $period->id,
            'quota' => 50,
        ]);

        $this->assertContains($response->getStatusCode(), [404, 405]);
    }

    public function test_admin_can_edit_product(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $product = Product::factory()->create([
            'name' => 'Original Name',
        ]);

        $response = $this->put("/admin/products/{$product->id}", [
            'name' => 'Updated Name',
            'description' => $product->description,
            'base_price' => $product->base_price,
            'size_options' => implode(', ', $product->size_options ?? []),
            'sweetness_options' => implode(', ', $product->sweetness_options ?? []),
        ]);

        $response->assertRedirect('/admin/products');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
        ]);
    }

    private function productPayload(array $extra = []): array
    {
        return array_merge([
            'name' => 'Maple Matcha Latte',
            'base_price' => 32000,
            'size_options' => '250 ml=32000, 1 liter=110000',
            'sweetness_options' => 'Manis normal, Less sugar',
        ], $extra);
    }

    public function test_admin_can_save_recipe_per_size(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $matcha = $this->ingredient('Matcha', 'g');
        $milk = $this->ingredient('Susu', 'ml');

        $this->post('/admin/products', $this->productPayload([
            'recipe' => [
                '250 ml' => [$matcha->id => '3', $milk->id => '200'],
                '1 liter' => [$matcha->id => '12.5', $milk->id => '800'],
            ],
        ]))->assertRedirect('/admin/products');

        $product = Product::where('name', 'Maple Matcha Latte')->first();
        $this->assertSame(4, $product->recipes()->count());
        $this->assertEquals(3, $product->recipes()->where('size', '250 ml')->where('ingredient_id', $matcha->id)->first()->amount);
        $this->assertEquals(12.5, $product->recipes()->where('size', '1 liter')->where('ingredient_id', $matcha->id)->first()->amount);
        $this->assertEquals(800, $product->recipes()->where('size', '1 liter')->where('ingredient_id', $milk->id)->first()->amount);
    }

    public function test_less_sugar_amount_saved_on_sugar_row(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $matcha = $this->ingredient('Matcha', 'g');
        $sugar = $this->ingredient('Gula', 'g', true);

        $this->post('/admin/products', $this->productPayload([
            'recipe' => ['250 ml' => [$matcha->id => '3', $sugar->id => '20']],
            'recipe_less_sugar' => ['250 ml' => '10', '1 liter' => '40'],
        ]))->assertRedirect('/admin/products');

        $product = Product::where('name', 'Maple Matcha Latte')->first();
        $sugarRow = $product->recipes()->where('ingredient_id', $sugar->id)->first();
        $this->assertEquals(20, $sugarRow->amount);
        $this->assertEquals(10, $sugarRow->amount_less_sugar);
        $this->assertNull($product->recipes()->where('ingredient_id', $matcha->id)->first()->amount_less_sugar);
        $this->assertSame(2, $product->recipes()->count());
    }

    public function test_blank_amounts_are_not_saved(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $matcha = $this->ingredient('Matcha', 'g');
        $milk = $this->ingredient('Susu', 'ml');

        $this->post('/admin/products', $this->productPayload([
            'recipe' => ['250 ml' => [$matcha->id => '', $milk->id => '0']],
        ]))->assertRedirect('/admin/products');

        $this->assertSame(0, Product::where('name', 'Maple Matcha Latte')->first()->recipes()->count());
    }

    public function test_recipe_for_removed_size_is_deleted(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $matcha = $this->ingredient('Matcha', 'g');
        $product = Product::factory()->create(['size_options' => ['250 ml', '1 liter'], 'size_prices' => null]);
        $this->recipe($product, '250 ml', $matcha, 3);
        $this->recipe($product, '1 liter', $matcha, 12);

        $this->put("/admin/products/{$product->id}", $this->productPayload([
            'size_options' => '250 ml=32000',
            'recipe' => [
                '250 ml' => [$matcha->id => '4'],
                '1 liter' => [$matcha->id => '12'],
            ],
        ]))->assertRedirect('/admin/products');

        $this->assertSame(1, $product->recipes()->count());
        $this->assertEquals(4, $product->recipes()->first()->amount);
        $this->assertSame('250 ml', $product->recipes()->first()->size);
    }

    public function test_product_without_sizes_saves_recipe_under_dash(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $matcha = $this->ingredient('Matcha', 'g');

        $this->post('/admin/products', $this->productPayload([
            'size_options' => '',
            'recipe' => ['-' => [$matcha->id => '5']],
        ]))->assertRedirect('/admin/products');

        $this->assertSame('-', Product::where('name', 'Maple Matcha Latte')->first()->recipes()->first()->size);
    }

    public function test_recipe_amounts_must_be_non_negative_numbers(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $matcha = $this->ingredient('Matcha', 'g');

        $this->post('/admin/products', $this->productPayload([
            'recipe' => ['250 ml' => [$matcha->id => '-1']],
        ]))->assertSessionHasErrors();

        $this->assertDatabaseCount('products', 0);
    }

    public function test_edit_form_shows_existing_recipe(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $matcha = $this->ingredient('Matcha', 'g');
        $sugar = $this->ingredient('Gula', 'g', true);
        $product = Product::factory()->create(['size_options' => ['250 ml'], 'size_prices' => null]);
        $this->recipe($product, '250 ml', $matcha, 3);
        $this->recipe($product, '250 ml', $sugar, 20, 10);

        $response = $this->get("/admin/products/{$product->id}/edit");

        $response->assertOk();
        $response->assertSee('name="recipe[250 ml]['.$matcha->id.']"', false);
        $response->assertSee('name="recipe_less_sugar[250 ml]"', false);
        $response->assertSee('value="3"', false);
        $response->assertSee('value="20"', false);
        $response->assertSee('value="10"', false);
        $response->assertSee('Less sugar');
        $response->assertSee('Simpan dulu, lalu isi resep untuk ukuran baru.');
    }

    public function test_form_without_sizes_shows_single_size_column(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $matcha = $this->ingredient('Matcha', 'g');

        $this->get('/admin/products/create')
            ->assertOk()
            ->assertSee('Satu ukuran')
            ->assertSee('name="recipe[-]['.$matcha->id.']"', false);
    }
}
