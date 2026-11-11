<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PoPeriod;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_order_by_number_and_whatsapp(): void
    {
        // Create a PO period
        $period = PoPeriod::create([
            'name' => 'PO Batch 1',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'status' => 'open',
        ]);

        // Create a product
        $product = Product::create([
            'category' => 'minuman',
            'name' => 'Matcha Latte',
            'description' => 'Matcha premium dengan susu segar.',
            'base_price' => 25000,
            'size_options' => ['250ml', '1L'],
            'sweetness_options' => ['Normal', 'Less Sugar'],
        ]);

        // Create an order
        $order = Order::create([
            'po_period_id' => $period->id,
            'customer_name' => 'Budi Santoso',
            'customer_whatsapp' => '081234567890',
            'order_number' => 'YC-1-ABCDEF',
            'payment_status' => 'pending',
            'order_status' => 'processing',
        ]);

        // Create an order item
        $order->items()->create([
            'product_id' => $product->id,
            'size' => '250ml',
            'sweetness' => 'Normal',
            'qty' => 2,
            'unit_price' => 25000,
        ]);

        // POST to lookup endpoint
        $response = $this->post('/orders/lookup', [
            'order_number' => 'YC-1-ABCDEF',
            'customer_whatsapp' => '081234567890',
        ]);

        // Assert successful response
        $response->assertStatus(200);

        // Assert response contains the order status
        $response->assertSee('YC-1-ABCDEF');
        $response->assertSee('processing');
        $response->assertSee('pending');
        $response->assertSee('Matcha Latte');
        $response->assertSee('2'); // quantity
    }

    public function test_finds_order_when_whatsapp_is_written_in_another_format(): void
    {
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'open']);

        Order::create([
            'po_period_id' => $period->id,
            'customer_name' => 'Budi Santoso',
            'customer_whatsapp' => '081234567890',
            'order_number' => 'YC-1-ABCDEF',
        ]);

        $this->post('/orders/lookup', [
            'order_number' => 'yc-1-abcdef',
            'customer_whatsapp' => '+62 812-3456-7890',
        ])->assertOk()->assertSee('YC-1-ABCDEF')->assertDontSee('Pesanannya nggak ketemu');
    }

    public function test_wrong_whatsapp_does_not_reveal_order(): void
    {
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'open']);

        Order::create([
            'po_period_id' => $period->id,
            'customer_name' => 'Budi Santoso',
            'customer_whatsapp' => '081234567890',
            'order_number' => 'YC-1-ABCDEF',
        ]);

        $this->post('/orders/lookup', [
            'order_number' => 'YC-1-ABCDEF',
            'customer_whatsapp' => '081299999999',
        ])->assertOk()->assertSee('Pesanannya nggak ketemu')->assertDontSee('Budi Santoso');
    }

    public function test_shows_not_found_for_no_match(): void
    {
        // POST to lookup endpoint with non-existent order
        $response = $this->post('/orders/lookup', [
            'order_number' => 'YC-999-NONEXISTENT',
            'customer_whatsapp' => '081234567890',
        ]);

        // Assert successful response (200, not 404/500)
        $response->assertStatus(200);

        // Assert friendly "not found" message
        $response->assertSee('Pesanannya nggak ketemu', false);
    }
}
