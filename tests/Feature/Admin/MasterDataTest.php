<?php

namespace Tests\Feature\Admin;

use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StocksMenu;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;
    use StocksMenu;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_master_page_shows_ingredients_and_sizes_together(): void
    {
        Ingredient::create(['name' => 'Matcha Tokyo', 'unit' => 'g', 'is_sugar' => false]);
        Size::create(['name' => '500 ml', 'volume_ml' => 500]);

        $this->actingAs($this->admin())
            ->get('/admin/master')
            ->assertOk()
            ->assertSee('Master Data')
            ->assertSee('id="bahan"', false)
            ->assertSee('id="ukuran"', false)
            ->assertSee('Matcha Tokyo')
            ->assertSee('500 ml');
    }

    public function test_guest_cannot_open_master_page(): void
    {
        $this->get('/admin/master')->assertRedirect('/admin/login');
    }

    public function test_old_pages_redirect_to_master_sections(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/ingredients')->assertRedirect('/admin/master#bahan');
        $this->get('/admin/sizes')->assertRedirect('/admin/master#ukuran');
    }

    public function test_unused_ingredient_can_be_deleted(): void
    {
        $ingredient = Ingredient::create(['name' => 'Cashew Milk', 'unit' => 'ml', 'is_sugar' => false]);

        $this->actingAs($this->admin())
            ->delete("/admin/ingredients/{$ingredient->id}")
            ->assertRedirect('/admin/master#bahan')
            ->assertSessionHas('status', 'Bahan berhasil dihapus.');

        $this->assertModelMissing($ingredient);
    }

    public function test_ingredient_used_in_a_recipe_cannot_be_deleted(): void
    {
        $milk = $this->ingredient('Susu');
        $this->recipe(Product::factory()->create(), '250 ml', $milk, 200);

        $this->actingAs($this->admin())
            ->delete("/admin/ingredients/{$milk->id}")
            ->assertRedirect('/admin/master#bahan')
            ->assertSessionHas('error');

        $this->assertModelExists($milk);
    }

    public function test_ingredient_stocked_in_a_period_cannot_be_deleted(): void
    {
        $milk = $this->ingredient('Susu');
        $this->stock(PoPeriod::create(['name' => 'PO', 'status' => 'draft']), $milk, 1000);

        $this->actingAs($this->admin())
            ->delete("/admin/ingredients/{$milk->id}")
            ->assertSessionHas('error');

        $this->assertModelExists($milk);
    }

    public function test_unused_size_can_be_deleted(): void
    {
        $size = Size::create(['name' => '2 liter', 'volume_ml' => 2000]);

        $this->actingAs($this->admin())
            ->delete("/admin/sizes/{$size->id}")
            ->assertRedirect('/admin/master#ukuran')
            ->assertSessionHas('status', 'Ukuran berhasil dihapus.');

        $this->assertModelMissing($size);
    }

    public function test_size_used_by_a_product_cannot_be_deleted(): void
    {
        $size = Size::create(['name' => '500 ml', 'volume_ml' => 500]);
        Product::factory()->create(['size_options' => ['500 ml']]);

        $this->actingAs($this->admin())
            ->delete("/admin/sizes/{$size->id}")
            ->assertRedirect('/admin/master#ukuran')
            ->assertSessionHas('error');

        $this->assertModelExists($size);
    }

    public function test_master_page_lists_products_with_edit_and_add_links(): void
    {
        $product = Product::factory()->create(['name' => 'Matcha Oat']);

        $this->actingAs($this->admin())
            ->get('/admin/master')
            ->assertOk()
            ->assertSee('id="produk"', false)
            ->assertSee('Matcha Oat')
            ->assertSee(route('admin.products.edit', $product), false)
            ->assertSee(route('admin.products.create'), false);
    }

    public function test_old_products_page_redirects_to_master_section(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/products')
            ->assertRedirect('/admin/master#produk');
    }

    public function test_product_never_ordered_can_be_deleted_with_its_recipes(): void
    {
        $product = Product::factory()->create();
        $this->recipe($product, '250 ml', $this->ingredient('Susu'), 200);
        PoPeriodProduct::create(['po_period_id' => PoPeriod::create(['name' => 'PO', 'status' => 'open'])->id, 'product_id' => $product->id]);

        $this->actingAs($this->admin())
            ->delete("/admin/products/{$product->id}")
            ->assertRedirect('/admin/master#produk')
            ->assertSessionHas('status', 'Produk berhasil dihapus.');

        $this->assertModelMissing($product);
        $this->assertDatabaseCount('product_recipes', 0);
        $this->assertDatabaseCount('po_period_products', 0);
    }

    public function test_product_with_orders_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();
        $order = Order::create([
            'po_period_id' => PoPeriod::create(['name' => 'PO', 'status' => 'open'])->id,
            'customer_name' => 'Budi',
            'customer_whatsapp' => '08123456789',
            'order_number' => 'YC-TEST-1',
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'size' => '250 ml', 'sweetness' => '-', 'qty' => 1, 'unit_price' => 10000]);

        $this->actingAs($this->admin())
            ->delete("/admin/products/{$product->id}")
            ->assertRedirect('/admin/master#produk')
            ->assertSessionHas('error');

        $this->assertModelExists($product);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_failed_size_add_does_not_prefill_ingredient_add_form(): void
    {
        $this->actingAs($this->admin());

        $this->from('/admin/master')
            ->post('/admin/sizes', ['_form' => 'size-new', 'name' => 'Botol Kaca', 'volume_ml' => 0])
            ->assertSessionHasErrors('volume_ml');

        $html = $this->get('/admin/master')->getContent();

        preg_match('/<form[^>]*ingredients"[^>]*>.*?<\/form>/s', $html, $ingredientAdd);
        $this->assertStringNotContainsString('Botol Kaca', $ingredientAdd[0]);
        $this->assertStringContainsString('value="Botol Kaca"', $html);
    }
}
