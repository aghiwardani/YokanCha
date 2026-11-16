<?php

namespace Tests\Feature;

use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StocksMenu;
use Tests\TestCase;

class ProductActiveTest extends TestCase
{
    use RefreshDatabase;
    use StocksMenu;

    private function sellable(string $name = 'Matcha Oat'): PoPeriodProduct
    {
        $period = PoPeriod::firstOrCreate(['name' => 'PO'], ['status' => 'open']);
        $product = Product::create(['name' => $name, 'base_price' => 30000, 'size_options' => ['250 ml'], 'sweetness_options' => ['Manis normal']]);
        $milk = $this->ingredient("Susu {$name}");
        $this->stock($period, $milk, 2000);
        $this->recipe($product, '250 ml', $milk, 200);

        return PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $product->id]);
    }

    private function addToCart(PoPeriodProduct $ppp)
    {
        return $this->post('/cart', ['po_period_product_id' => $ppp->id, 'qty' => 1, 'size' => '250 ml', 'sweetness' => 'Manis normal']);
    }

    public function test_products_are_active_by_default(): void
    {
        $this->assertTrue(Product::create(['name' => 'Latte', 'base_price' => 1])->fresh()->is_active);
    }

    public function test_inactive_product_is_hidden_from_the_menu(): void
    {
        $this->sellable('Matcha Oat');
        $this->sellable('Matcha Murni')->product->update(['is_active' => false]);

        $this->get('/')->assertOk()->assertSee('Matcha Oat')->assertDontSee('Matcha Murni');
    }

    public function test_inactive_product_cannot_be_added_to_cart(): void
    {
        $ppp = $this->sellable();
        $ppp->product->update(['is_active' => false]);

        $this->addToCart($ppp)->assertSessionHasErrors('po_period_product_id');
        $this->assertSame([], session('cart', []));
    }

    public function test_checkout_rejects_a_product_deactivated_after_it_was_added(): void
    {
        $ppp = $this->sellable();
        $this->addToCart($ppp)->assertSessionHasNoErrors();
        $ppp->product->update(['is_active' => false]);

        $this->post('/checkout', ['customer_name' => 'Budi', 'customer_whatsapp' => '081234567890'])
            ->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_admin_can_deactivate_and_reactivate_a_product(): void
    {
        $product = Product::create(['name' => 'Latte', 'base_price' => 1]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->patch("/admin/products/{$product->id}/active", ['is_active' => '0'])
            ->assertRedirect('/admin/master#produk')
            ->assertSessionHas('status', 'Latte dinonaktifkan. Produk ini tidak tampil di menu dan tidak bisa dipesan.');
        $this->assertFalse($product->fresh()->is_active);

        $this->get('/admin/master')->assertSee('Nonaktif')->assertSee('Aktifkan');

        $this->patch("/admin/products/{$product->id}/active", ['is_active' => '1'])
            ->assertSessionHas('status', 'Latte diaktifkan lagi.');
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_new_period_includes_only_active_products(): void
    {
        $active = Product::create(['name' => 'Latte', 'base_price' => 1]);
        Product::create(['name' => 'Lama', 'base_price' => 1, 'is_active' => false]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->post('/admin/periods', ['name' => 'Batch baru']);

        $this->assertSame([$active->id], PoPeriodProduct::pluck('product_id')->all());
    }
}
