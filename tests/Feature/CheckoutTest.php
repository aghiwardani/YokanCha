<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function makePoPeriodProduct(int $quota = 5, int $orderedQty = 0): PoPeriodProduct
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
            'quota' => $quota,
            'ordered_qty' => $orderedQty,
        ]);
    }

    private function addToCart(PoPeriodProduct $ppp, int $qty): void
    {
        $this->post('/cart', [
            'po_period_product_id' => $ppp->id,
            'qty' => $qty,
            'size' => '250ml',
            'sweetness' => 'Normal',
        ]);
    }

    public function test_submits_order_with_sufficient_quota(): void
    {
        $ppp = $this->makePoPeriodProduct(quota: 5, orderedQty: 0);

        $this->addToCart($ppp, 2);

        $response = $this->post('/checkout', [
            'customer_name' => 'Budi Santoso',
            'customer_whatsapp' => '081234567890',
        ]);

        $this->assertDatabaseCount('orders', 1);

        $order = Order::first();

        $response->assertRedirect(route('checkout.confirmation', $order->order_number));

        $this->assertSame('Budi Santoso', $order->customer_name);
        $this->assertSame('081234567890', $order->customer_whatsapp);
        $this->assertSame($ppp->po_period_id, $order->po_period_id);

        $this->assertDatabaseCount('order_items', 1);
        $item = $order->items()->first();
        $this->assertSame($ppp->product_id, $item->product_id);
        $this->assertSame(2, $item->qty);
        $this->assertSame(25000, $item->unit_price);

        $this->assertSame(2, $ppp->fresh()->ordered_qty);
    }

    public function test_rejects_whatsapp_number_that_is_not_a_phone_number(): void
    {
        $ppp = $this->makePoPeriodProduct(quota: 5);
        $this->addToCart($ppp, 1);

        $this->post('/checkout', [
            'customer_name' => 'Budi Santoso',
            'customer_whatsapp' => 'abc',
        ])->assertSessionHasErrors('customer_whatsapp');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_confirmation_shows_payment_settings_and_whatsapp_link(): void
    {
        config([
            'yokancha.whatsapp' => '6289900001111',
            'yokancha.payment.bank' => 'Bank Contoh',
            'yokancha.payment.account_number' => '9876543210',
            'yokancha.payment.account_name' => 'Pemilik YokanCha',
        ]);

        $ppp = $this->makePoPeriodProduct(quota: 5);
        $this->addToCart($ppp, 2);

        $this->post('/checkout', [
            'customer_name' => 'Budi Santoso',
            'customer_whatsapp' => '081234567890',
        ]);

        $order = Order::first();

        $response = $this->get(route('checkout.confirmation', $order->order_number));

        $response->assertOk()
            ->assertSee('Bank Contoh')
            ->assertSee('9876543210')
            ->assertSee('Pemilik YokanCha')
            ->assertSee('https://wa.me/6289900001111?text=', false);

        // The prefilled message carries the order number, items, and total.
        preg_match('#https://wa\.me/6289900001111\?text=([^"]+)#', $response->getContent(), $match);
        $message = rawurldecode(html_entity_decode($match[1]));

        $this->assertStringContainsString($order->order_number, $message);
        $this->assertStringContainsString('2x Matcha Latte', $message);
        $this->assertStringContainsString('Rp50.000', $message);
    }

    public function test_order_item_uses_price_of_selected_size(): void
    {
        $ppp = $this->makePoPeriodProduct(quota: 5);
        $ppp->product->update(['size_prices' => ['250ml' => 32000, '1L' => 110000]]);

        $this->post('/cart', [
            'po_period_product_id' => $ppp->id,
            'qty' => 2,
            'size' => '1L',
            'sweetness' => 'Normal',
        ]);

        $this->get('/cart')->assertSee('220.000');

        $this->post('/checkout', [
            'customer_name' => 'Budi Santoso',
            'customer_whatsapp' => '081234567890',
        ]);

        $item = Order::first()->items()->first();
        $this->assertSame('1L', $item->size);
        $this->assertSame(110000, $item->unit_price);
    }

    public function test_rejects_checkout_when_quota_insufficient_at_submit_time(): void
    {
        $ppp = $this->makePoPeriodProduct(quota: 5, orderedQty: 0);

        $this->addToCart($ppp, 3);

        // Other customers buy most of the stock before this one checks out,
        // so the cart line now asks for more than the remaining quota (1).
        $ppp->update(['ordered_qty' => 4]);

        $response = $this->post('/checkout', [
            'customer_name' => 'Budi Santoso',
            'customer_whatsapp' => '081234567890',
        ]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(4, $ppp->fresh()->ordered_qty);

        $response->assertSessionHasErrors();
        $response->assertSessionHas('errors', function ($errors) {
            return str_contains(implode(' ', $errors->all()), 'Matcha Latte');
        });
    }

    public function test_rejects_checkout_when_period_closed_after_adding_to_cart(): void
    {
        $ppp = $this->makePoPeriodProduct(quota: 5, orderedQty: 0);

        $this->addToCart($ppp, 2);

        // Admin closes the period after the customer already added to cart.
        $ppp->poPeriod->update(['status' => 'closed']);

        $response = $this->post('/checkout', [
            'customer_name' => 'Budi Santoso',
            'customer_whatsapp' => '081234567890',
        ]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(0, $ppp->fresh()->ordered_qty);

        $response->assertSessionHasErrors('cart');
    }

    public function test_rejects_checkout_when_cart_line_belongs_to_non_open_period(): void
    {
        // Open period (currently open) and a separate, non-open period whose
        // product line will sit stale in the cart.
        $openPeriod = PoPeriod::create([
            'name' => 'PO Batch 2',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'status' => 'open',
        ]);

        $staleProduct = Product::create([
            'category' => 'minuman',
            'name' => 'Houjicha Latte',
            'description' => 'Houjicha hangat.',
            'base_price' => 22000,
            'size_options' => ['250ml'],
            'sweetness_options' => ['Normal'],
        ]);

        $stalePeriod = PoPeriod::create([
            'name' => 'PO Batch Lama',
            'opens_at' => now()->subDays(5),
            'closes_at' => now()->subDays(2),
            'status' => 'closed',
        ]);

        $stalePpp = PoPeriodProduct::create([
            'po_period_id' => $stalePeriod->id,
            'product_id' => $staleProduct->id,
            'quota' => 5,
            'ordered_qty' => 0,
        ]);

        $this->addToCart($stalePpp, 1);

        $response = $this->post('/checkout', [
            'customer_name' => 'Budi Santoso',
            'customer_whatsapp' => '081234567890',
        ]);

        $this->assertDatabaseCount('orders', 0);
        $response->assertSessionHasErrors('cart');
    }

    public function test_requires_name_and_whatsapp_number(): void
    {
        $ppp = $this->makePoPeriodProduct();

        $this->addToCart($ppp, 1);

        $response = $this->post('/checkout', [
            'customer_name' => 'Budi Santoso',
        ]);

        $response->assertSessionHasErrors('customer_whatsapp');
        $this->assertDatabaseCount('orders', 0);
    }
}
