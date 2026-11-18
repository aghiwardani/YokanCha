<?php

namespace Tests\Feature;

use App\Models\PoPeriod;
use App\Models\PoPeriodIngredient;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StocksMenu;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;
    use StocksMenu;

    private $milk;

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

        $this->milk = $this->ingredient('Susu');
        $this->stock($period, $this->milk, 100000);
        $this->recipe($product, '250ml', $this->milk, 200);
        $this->recipe($product, '1L', $this->milk, 1000);

        return PoPeriodProduct::create([
            'po_period_id' => $period->id,
            'product_id' => $product->id,
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

    public function test_menu_card_has_quantity_field_capped_at_max_per_variant(): void
    {
        $this->makePoPeriodProduct();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*type="number"[^>]*name="qty"[^>]*value="1"[^>]*min="1"[^>]*max="'.config('yokancha.max_per_variant').'"/s', $html);
        $this->assertStringContainsString('data-qty-step="1"', $html);
        $this->assertStringContainsString('data-qty-step="-1"', $html);
    }

    public function test_adding_via_ajax_returns_cart_count_without_redirect(): void
    {
        $ppp = $this->makePoPeriodProduct();

        $this->postJson('/cart', $this->cartPayload($ppp, ['qty' => 2]))
            ->assertOk()
            ->assertJson([
                'count' => 2,
                'message' => '2 botol Matcha Latte masuk keranjang.',
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

    public function test_cannot_add_more_than_ingredients_allow(): void
    {
        $ppp = $this->makePoPeriodProduct();
        PoPeriodIngredient::query()->update(['stock' => 1000]);

        $this->post('/cart', $this->cartPayload($ppp, ['qty' => 6]))
            ->assertSessionHasErrors(['qty' => 'Stok Matcha Latte ukuran 250ml tinggal cukup buat 5 botol lagi.']);
        $this->post('/cart', $this->cartPayload($ppp, ['qty' => 5]))->assertSessionHasNoErrors();
    }

    public function test_sold_out_variant_says_habis(): void
    {
        $ppp = $this->makePoPeriodProduct();
        PoPeriodIngredient::query()->update(['stock' => 100]);

        $this->post('/cart', $this->cartPayload($ppp))
            ->assertSessionHasErrors(['qty' => 'Matcha Latte sudah habis untuk batch ini.']);
    }

    public function test_cart_sums_shared_ingredient_across_lines(): void
    {
        $ppp = $this->makePoPeriodProduct();
        PoPeriodIngredient::query()->update(['stock' => 1000]);

        $this->post('/cart', $this->cartPayload($ppp, ['qty' => 3, 'sweetness' => 'Normal']))->assertSessionHasNoErrors();
        $this->post('/cart', $this->cartPayload($ppp, ['qty' => 3, 'sweetness' => 'Less Sugar']))->assertSessionHasErrors('qty');

        $this->assertSame(3, app(\App\Services\CartService::class)->count());
    }

    public function test_cart_checks_shared_ingredient_across_products(): void
    {
        $ppp = $this->makePoPeriodProduct();
        PoPeriodIngredient::query()->update(['stock' => 1000]);

        $other = Product::create(['name' => 'Hojicha', 'base_price' => 25000, 'size_options' => ['250ml']]);
        $this->recipe($other, '250ml', $this->milk, 200);
        $otherPpp = PoPeriodProduct::create(['po_period_id' => $ppp->po_period_id, 'product_id' => $other->id]);

        $this->post('/cart', $this->cartPayload($ppp, ['qty' => 3]))->assertSessionHasNoErrors();
        $this->post('/cart', $this->cartPayload($otherPpp, ['qty' => 3, 'sweetness' => '-']))
            ->assertSessionHasErrors(['qty' => 'Stok Hojicha ukuran 250ml tinggal cukup buat 2 botol lagi.']);
    }

    public function test_update_checks_ingredients_excluding_its_own_line(): void
    {
        $ppp = $this->makePoPeriodProduct();
        PoPeriodIngredient::query()->update(['stock' => 1000]);

        $this->post('/cart', $this->cartPayload($ppp, ['qty' => 2]));

        $this->patch("/cart/{$ppp->id}|250ml|Normal", ['qty' => 5])->assertSessionHasNoErrors();
        $this->patch("/cart/{$ppp->id}|250ml|Normal", ['qty' => 6])->assertSessionHasErrors('qty');
    }

    public function test_update_cannot_raise_a_variant_above_twenty(): void
    {
        $ppp = $this->makePoPeriodProduct();

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
