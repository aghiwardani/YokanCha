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

    private function cartPayload(PoPeriodProduct $ppp, array $overrides = []): array
    {
        return array_merge([
            'po_period_product_id' => $ppp->id,
            'qty' => 1,
            'size' => '250ml',
            'sweetness' => 'Normal',
        ], $overrides);
    }

    public function test_rejects_item_from_a_period_that_is_not_open(): void
    {
        $ppp = $this->makePoPeriodProduct();
        $ppp->poPeriod->update(['status' => 'closed']);

        $this->post('/cart', $this->cartPayload($ppp))->assertSessionHasErrors('po_period_product_id');

        $this->assertSame([], session('cart', []));
    }

    public function test_rejects_size_or_sweetness_not_offered_by_product(): void
    {
        $ppp = $this->makePoPeriodProduct();

        $this->post('/cart', $this->cartPayload($ppp, ['size' => '5 liter']))->assertSessionHasErrors('size');
        $this->post('/cart', $this->cartPayload($ppp, ['sweetness' => 'Extra manis']))->assertSessionHasErrors('sweetness');

        $this->assertSame([], session('cart', []));
    }

    public function test_limits_each_variant_to_twenty_bottles_across_sizes(): void
    {
        $ppp = $this->makePoPeriodProduct();
        $ppp->update(['quota' => 100]);

        $this->post('/cart', $this->cartPayload($ppp, ['qty' => 15, 'size' => '250ml']))->assertSessionHasNoErrors();
        $this->post('/cart', $this->cartPayload($ppp, ['qty' => 6, 'size' => '1L']))->assertSessionHasErrors('qty');

        $this->assertSame(15, app(\App\Services\CartService::class)->count());
    }

    public function test_adding_from_menu_stays_on_menu_with_confirmation(): void
    {
        $ppp = $this->makePoPeriodProduct();

        $this->from(route('menu.index'))
            ->post('/cart', $this->cartPayload($ppp))
            ->assertRedirect(route('menu.index').'#menu')
            ->assertSessionHas('cart_status', 'Matcha Latte masuk keranjang.');

        $this->assertSame(1, app(\App\Services\CartService::class)->count());
    }

    public function test_adding_via_ajax_returns_cart_count_without_redirect(): void
    {
        $ppp = $this->makePoPeriodProduct();

        $this->postJson('/cart', $this->cartPayload($ppp, ['qty' => 2]))
            ->assertOk()
            ->assertJson([
                'count' => 2,
                'message' => 'Matcha Latte masuk keranjang.',
            ]);

        $this->postJson('/cart', $this->cartPayload($ppp))
            ->assertOk()
            ->assertJson(['count' => 3]);
    }

    public function test_failed_ajax_add_returns_validation_errors(): void
    {
        $ppp = $this->makePoPeriodProduct();

        $this->postJson('/cart', $this->cartPayload($ppp, ['size' => '5 liter']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('size');
    }

    public function test_failed_add_from_menu_returns_to_menu_section(): void
    {
        $ppp = $this->makePoPeriodProduct();

        $this->from(route('menu.index'))
            ->post('/cart', $this->cartPayload($ppp, ['size' => '5 liter']))
            ->assertRedirect(route('menu.index').'#menu');
    }

    public function test_cannot_add_more_than_remaining_quota(): void
    {
        $ppp = $this->makePoPeriodProduct();
        $ppp->update(['quota' => 5, 'ordered_qty' => 3]);

        $this->post('/cart', $this->cartPayload($ppp, ['qty' => 3]))->assertSessionHasErrors('qty');
        $this->post('/cart', $this->cartPayload($ppp, ['qty' => 2]))->assertSessionHasNoErrors();
    }

    public function test_update_cannot_raise_a_variant_above_twenty(): void
    {
        $ppp = $this->makePoPeriodProduct();
        $ppp->update(['quota' => 100]);

        $this->post('/cart', $this->cartPayload($ppp, ['qty' => 2]));

        $this->patch("/cart/{$ppp->id}|250ml|Normal", ['qty' => 21])->assertSessionHasErrors('qty');

        $this->assertSame(2, app(\App\Services\CartService::class)->count());
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
