<?php

namespace Tests\Feature\Admin;

use App\Models\PoPeriod;
use App\Models\Product;
use App\Models\Size;
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
            'sizes' => $this->sizes(['250ml' => null, '1L' => null]),
            'sweetness_options' => 'Normal, Kurang Gula',
        ]);

        $response->assertRedirect('/admin/master#produk');

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
            'sizes' => $this->sizes(['250 ml' => 32000, '1 liter' => 110000]),
            'sweetness_options' => 'Manis normal, Less sugar',
            'tag' => 'Maple Signature',
            'tone' => 'amber',
            'bottle_style' => 'maple',
            'sort_order' => 1,
        ])->assertRedirect('/admin/master#produk');

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
            'sizes' => $this->sizes(array_fill_keys($product->size_options ?? [], null)),
            'sweetness_options' => implode(', ', $product->sweetness_options ?? []),
        ]);

        $response->assertRedirect('/admin/master#produk');

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
            'sizes' => $this->sizes(['250 ml' => 32000, '1 liter' => 110000]),
            'sweetness_options' => 'Manis normal, Less sugar',
        ], $extra);
    }

    /**
     * Form payload for the size checkboxes: creates any missing master sizes
     * (volume guessed from the name) and ticks them with the given prices.
     *
     * @param  array<string, int|null>  $prices  size name => price (null = base price)
     */
    private function sizes(array $prices): array
    {
        $payload = [];

        foreach ($prices as $name => $price) {
            $size = Size::firstOrCreate(['name' => $name], ['volume_ml' => Size::volumeFromName($name)]);
            $payload[$size->id] = ['on' => '1', 'price' => $price === null ? '' : (string) $price];
        }

        return $payload;
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
        ]))->assertRedirect('/admin/master#produk');

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
            'recipe_less_sugar' => ['250 ml' => [$sugar->id => '10'], '1 liter' => [$sugar->id => '40']],
        ]))->assertRedirect('/admin/master#produk');

        $product = Product::where('name', 'Maple Matcha Latte')->first();
        $sugarRow = $product->recipes()->where('ingredient_id', $sugar->id)->first();
        $this->assertEquals(20, $sugarRow->amount);
        $this->assertEquals(10, $sugarRow->amount_less_sugar);
        $this->assertNull($product->recipes()->where('ingredient_id', $matcha->id)->first()->amount_less_sugar);
        $this->assertSame(2, $product->recipes()->count());
    }

    public function test_amounts_typed_the_indonesian_way_are_read_correctly(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $matcha = $this->ingredient('Matcha', 'g');
        $milk = $this->ingredient('Susu', 'ml');

        $this->post('/admin/products', $this->productPayload([
            'base_price' => '1.250.000',
            'recipe' => ['250 ml' => [$matcha->id => '2,5', $milk->id => '1.000']],
        ]))->assertRedirect('/admin/master#produk')->assertSessionHasNoErrors();

        $product = Product::where('name', 'Maple Matcha Latte')->first();
        $this->assertSame(1250000, $product->base_price);
        $this->assertEquals(2.5, $product->recipes()->where('ingredient_id', $matcha->id)->value('amount'));
        $this->assertEquals(1000, $product->recipes()->where('ingredient_id', $milk->id)->value('amount'));
    }

    public function test_each_sugar_ingredient_has_its_own_less_sugar_amount(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $maple = $this->ingredient('Maple Syrup', 'ml', true);
        $simple = $this->ingredient('Simple Syrup', 'ml', true);

        $this->post('/admin/products', $this->productPayload([
            'recipe' => ['250 ml' => [$maple->id => '20', $simple->id => '10']],
            'recipe_less_sugar' => ['250 ml' => [$maple->id => '12', $simple->id => '4']],
        ]))->assertRedirect('/admin/master#produk');

        $product = Product::where('name', 'Maple Matcha Latte')->first();
        $this->assertEquals(12, $product->recipes()->where('ingredient_id', $maple->id)->first()->amount_less_sugar);
        $this->assertEquals(4, $product->recipes()->where('ingredient_id', $simple->id)->first()->amount_less_sugar);

        $this->get("/admin/products/{$product->id}/edit")
            ->assertSee('name="recipe_less_sugar[250 ml]['.$maple->id.']"', false)
            ->assertSee('name="recipe_less_sugar[250 ml]['.$simple->id.']"', false);
    }

    public function test_blank_amounts_are_not_saved(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $matcha = $this->ingredient('Matcha', 'g');
        $milk = $this->ingredient('Susu', 'ml');

        $this->post('/admin/products', $this->productPayload([
            'recipe' => ['250 ml' => [$matcha->id => '', $milk->id => '0']],
        ]))->assertRedirect('/admin/master#produk');

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
            'sizes' => $this->sizes(['250 ml' => 32000]),
            'recipe' => [
                '250 ml' => [$matcha->id => '4'],
                '1 liter' => [$matcha->id => '12'],
            ],
        ]))->assertRedirect('/admin/master#produk');

        $this->assertSame(1, $product->recipes()->count());
        $this->assertEquals(4, $product->recipes()->first()->amount);
        $this->assertSame('250 ml', $product->recipes()->first()->size);
    }

    public function test_product_without_sizes_saves_recipe_under_dash(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $matcha = $this->ingredient('Matcha', 'g');

        $this->post('/admin/products', $this->productPayload([
            'sizes' => [],
            'recipe' => ['-' => [$matcha->id => '5']],
        ]))->assertRedirect('/admin/master#produk');

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
        Size::create(['name' => '250 ml', 'volume_ml' => 250]);
        $product = Product::factory()->create(['size_options' => ['250 ml'], 'size_prices' => null]);
        $this->recipe($product, '250 ml', $matcha, 3);
        $this->recipe($product, '250 ml', $sugar, 20, 10);

        $response = $this->get("/admin/products/{$product->id}/edit");

        $response->assertOk();
        $response->assertSee('name="recipe[250 ml]['.$matcha->id.']"', false);
        $response->assertSee('name="recipe_less_sugar[250 ml]['.$sugar->id.']"', false);
        $response->assertSee('value="3"', false);
        $response->assertSee('value="20"', false);
        $response->assertSee('value="10"', false);
        $response->assertSee('Less sugar');
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

    public function test_recipe_amount_above_column_limit_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $milk = $this->ingredient('Susu');

        $this->post('/admin/products', [
            'name' => 'Latte', 'base_price' => 10000, 'sizes' => $this->sizes(['250ml' => null]),
            'recipe' => ['250ml' => [$milk->id => '1000000000']],
        ])->assertSessionHasErrors('recipe');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_non_array_recipe_input_fails_validation(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post('/admin/products', [
            'name' => 'Latte', 'base_price' => 10000, 'recipe' => 'abc', 'recipe_less_sugar' => 'abc',
        ])->assertSessionHasErrors(['recipe', 'recipe_less_sugar']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_sizes_are_saved_smallest_first_whatever_the_order_ticked(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $liter = Size::create(['name' => '1 liter', 'volume_ml' => 1000]);
        $small = Size::create(['name' => '250 ml', 'volume_ml' => 250]);
        $mid = Size::create(['name' => '500 ml', 'volume_ml' => 500]);

        $this->post('/admin/products', $this->productPayload([
            'sizes' => [
                $liter->id => ['on' => '1', 'price' => '110000'],
                $small->id => ['on' => '1', 'price' => '32000'],
                $mid->id => ['on' => '1', 'price' => '60000'],
            ],
        ]))->assertRedirect('/admin/master#produk');

        $product = Product::where('name', 'Maple Matcha Latte')->first();
        $this->assertSame(['250 ml', '500 ml', '1 liter'], $product->size_options);
        $this->assertSame(60000, $product->priceFor('500 ml'));
    }

    public function test_unticked_size_is_not_saved_even_with_a_price(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $small = Size::create(['name' => '250 ml', 'volume_ml' => 250]);
        $mid = Size::create(['name' => '500 ml', 'volume_ml' => 500]);

        $this->post('/admin/products', $this->productPayload([
            'sizes' => [
                $small->id => ['on' => '1', 'price' => '32000'],
                $mid->id => ['price' => '60000'],
            ],
        ]))->assertRedirect('/admin/master#produk');

        $product = Product::where('name', 'Maple Matcha Latte')->first();
        $this->assertSame(['250 ml'], $product->size_options);
        $this->assertSame(['250 ml' => 32000], $product->size_prices);
    }

    public function test_unknown_size_id_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post('/admin/products', $this->productPayload([
            'sizes' => [999 => ['on' => '1', 'price' => '1000']],
        ]))->assertSessionHasErrors('sizes');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_form_lists_every_master_size_with_checkbox_and_recipe_column(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $matcha = $this->ingredient('Matcha', 'g');
        Size::create(['name' => '1 liter', 'volume_ml' => 1000]);
        $mid = Size::create(['name' => '500 ml', 'volume_ml' => 500]);
        $small = Size::create(['name' => '250 ml', 'volume_ml' => 250]);
        $product = Product::factory()->create(['size_options' => ['250 ml'], 'size_prices' => ['250 ml' => 32000]]);

        $response = $this->get("/admin/products/{$product->id}/edit")->assertOk();

        $response->assertSeeInOrder(['250 ml', '500 ml', '1 liter']);
        $response->assertSee('name="sizes['.$small->id.'][on]"', false);
        $response->assertSee('name="sizes['.$mid->id.'][price]"', false);
        $response->assertSee('name="recipe[500 ml]['.$matcha->id.']"', false);
        $this->assertMatchesRegularExpression('/name="sizes\['.$small->id.'\]\[on\]"[^>]*checked/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/name="sizes\['.$mid->id.'\]\[on\]"[^>]*checked/', $response->getContent());
    }

    public function test_size_missing_from_master_is_kept_on_save_and_flagged(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $milk = $this->ingredient('Susu');
        $small = Size::create(['name' => '250 ml', 'volume_ml' => 250]);
        $product = Product::factory()->create([
            'size_options' => ['250 ml', 'Jumbo'],
            'size_prices' => ['250 ml' => 30000, 'Jumbo' => 50000],
        ]);
        $this->recipe($product, 'Jumbo', $milk, 600);

        $this->get("/admin/products/{$product->id}/edit")
            ->assertOk()
            ->assertSee('Jumbo belum ada di daftar Ukuran')
            ->assertSee('name="recipe[Jumbo]['.$milk->id.']"', false);

        $this->put("/admin/products/{$product->id}", $this->productPayload([
            'name' => $product->name,
            'sizes' => [$small->id => ['on' => '1', 'price' => '30000']],
            'recipe' => ['Jumbo' => [$milk->id => '600']],
        ]))->assertRedirect('/admin/master#produk');

        $product->refresh();
        $this->assertSame(['250 ml', 'Jumbo'], $product->size_options);
        $this->assertSame(50000, $product->priceFor('Jumbo'));
        $this->assertSame(1, $product->recipes()->where('size', 'Jumbo')->count());
    }

    public function test_price_with_thousand_dots_is_accepted(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $small = Size::create(['name' => '250 ml', 'volume_ml' => 250]);

        $this->post('/admin/products', $this->productPayload([
            'sizes' => [$small->id => ['on' => '1', 'price' => '32.000']],
        ]))->assertRedirect('/admin/master#produk');

        $this->assertSame(32000, Product::where('name', 'Maple Matcha Latte')->first()->priceFor('250 ml'));
    }

    public function test_prefilled_recipe_column_is_marked_otomatis(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $milk = $this->ingredient('Susu');
        Size::create(['name' => '250 ml', 'volume_ml' => 250]);
        Size::create(['name' => '500 ml', 'volume_ml' => 500]);
        $product = Product::factory()->create(['size_options' => ['250 ml'], 'size_prices' => null]);
        $this->recipe($product, '250 ml', $milk, 200);

        $html = $this->get("/admin/products/{$product->id}/edit")->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/500 ml\s*<span[^>]*>\(otomatis\)/', $html);
        $this->assertDoesNotMatchRegularExpression('/250 ml\s*<span[^>]*>\(otomatis\)/', $html);
    }

    public function test_new_size_recipe_is_prefilled_by_scaling_the_smallest_recipe(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $matcha = $this->ingredient('Matcha', 'g');
        $milk = $this->ingredient('Susu', 'ml');
        $sugar = $this->ingredient('Gula', 'ml', true);
        Size::create(['name' => '250 ml', 'volume_ml' => 250]);
        Size::create(['name' => '500 ml', 'volume_ml' => 500]);
        Size::create(['name' => '1 liter', 'volume_ml' => 1000]);
        $product = Product::factory()->create(['size_options' => ['250 ml', '1 liter'], 'size_prices' => null]);
        $this->recipe($product, '250 ml', $matcha, 4);
        $this->recipe($product, '250 ml', $milk, 200);
        $this->recipe($product, '250 ml', $sugar, 15, 7.5);
        $this->recipe($product, '1 liter', $matcha, 15); // saved recipe wins over scaling

        $html = $this->get("/admin/products/{$product->id}/edit")->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="recipe\[500 ml\]\['.$matcha->id.'\]"\s+value="8"/', $html);
        $this->assertMatchesRegularExpression('/name="recipe\[500 ml\]\['.$milk->id.'\]"\s+value="400"/', $html);
        $this->assertMatchesRegularExpression('/name="recipe\[500 ml\]\['.$sugar->id.'\]"\s+value="30"/', $html);
        $this->assertMatchesRegularExpression('/name="recipe_less_sugar\[500 ml\]\['.$sugar->id.'\]"\s+value="15"/', $html);
        $this->assertMatchesRegularExpression('/name="recipe\[1 liter\]\['.$matcha->id.'\]"\s+value="15"/', $html);
        $this->assertMatchesRegularExpression('/name="recipe\[1 liter\]\['.$milk->id.'\]"\s+value=""/', $html);
    }
}
