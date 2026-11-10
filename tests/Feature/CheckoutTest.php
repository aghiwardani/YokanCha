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

    public function test_rejects_checkout_when_quota_insufficient_at_submit_time(): void
    {
        $ppp = $this->makePoPeriodProduct(quota: 5, orderedQty: 4);

        // Cart line requests more than the remaining quota (1).
        $this->addToCart($ppp, 3);

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
