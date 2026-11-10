<?php

namespace Tests\Feature;

use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function makePoPeriodProduct(): PoPeriodProduct
    {
        $period = PoPeriod::create([
            'name' => 'PO Batch 1',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'status' => 'open',
        ]);

        $product = Product::create([
            'category' => 'minuman',
            'name' => 'Matcha Latte',
            'description' => 'Matcha premium dengan susu segar.',
            'base_price' => 25000,
            'size_options' => ['250ml', '1L'],
            'sweetness_options' => ['Normal', 'Less Sugar'],
        ]);

        return PoPeriodProduct::create([
            'po_period_id' => $period->id,
            'product_id' => $product->id,
            'quota' => 10,
            'ordered_qty' => 0,
        ]);
    }

    public function test_add_to_cart_then_view_shows_item_and_subtotal(): void
    {
        $ppp = $this->makePoPeriodProduct();

        $this->post('/cart', [
            'po_period_product_id' => $ppp->id,
            'qty' => 2,
            'size' => '250ml',
            'sweetness' => 'Normal',
        ])->assertRedirect();

        $response = $this->get('/cart');

        $response->assertStatus(200);
        $response->assertSee('Matcha Latte');
        $response->assertSee('2');
        // subtotal = 25000 * 2 = 50000
        $response->assertSee('50.000');
    }

    public function test_adding_same_variant_twice_merges_quantity(): void
    {
        $ppp = $this->makePoPeriodProduct();

        $payload = [
            'po_period_product_id' => $ppp->id,
            'qty' => 2,
            'size' => '250ml',
            'sweetness' => 'Normal',
        ];

        $this->post('/cart', $payload);
        $this->post('/cart', $payload);

        $response = $this->get('/cart');

        $response->assertStatus(200);
        $response->assertSee('4');
        // subtotal = 25000 * 4 = 100000
        $response->assertSee('100.000');
    }

    public function test_update_line_quantity(): void
    {
        $ppp = $this->makePoPeriodProduct();

        $this->post('/cart', [
            'po_period_product_id' => $ppp->id,
            'qty' => 2,
            'size' => '250ml',
            'sweetness' => 'Normal',
        ]);

        $lineKey = "{$ppp->id}|250ml|Normal";

        $this->patch("/cart/{$lineKey}", ['qty' => 5])->assertRedirect();

        $response = $this->get('/cart');
        $response->assertStatus(200);
        $response->assertSee('5');
        // subtotal = 25000 * 5 = 125000
        $response->assertSee('125.000');
    }

    public function test_update_line_quantity_to_zero_removes_it(): void
    {
        $ppp = $this->makePoPeriodProduct();

        $this->post('/cart', [
            'po_period_product_id' => $ppp->id,
            'qty' => 2,
            'size' => '250ml',
            'sweetness' => 'Normal',
        ]);

        $lineKey = "{$ppp->id}|250ml|Normal";

        $this->patch("/cart/{$lineKey}", ['qty' => 0])->assertRedirect();

        $response = $this->get('/cart');
        $response->assertStatus(200);
        $response->assertDontSee('Matcha Latte');
    }

    public function test_remove_line_from_cart(): void
    {
        $ppp = $this->makePoPeriodProduct();

        $this->post('/cart', [
            'po_period_product_id' => $ppp->id,
            'qty' => 1,
            'size' => '250ml',
            'sweetness' => 'Normal',
        ]);

        $lineKey = "{$ppp->id}|250ml|Normal";

        $this->delete("/cart/{$lineKey}")->assertRedirect();

        $response = $this->get('/cart');

        $response->assertStatus(200);
        $response->assertDontSee('Matcha Latte');
    }
}
