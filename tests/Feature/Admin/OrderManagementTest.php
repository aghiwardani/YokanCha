<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrderWithReservedQuota(int $qty = 3): array
    {
        $period = PoPeriod::factory()->create();
        $product = Product::factory()->create();

        $ppp = PoPeriodProduct::create([
            'po_period_id' => $period->id,
            'product_id' => $product->id,
            'quota' => 10,
            'ordered_qty' => $qty,
        ]);

        $order = Order::create([
            'po_period_id' => $period->id,
            'customer_name' => 'Budi',
            'customer_whatsapp' => '08123456789',
            'payment_status' => 'unpaid',
            'order_status' => 'new',
            'order_number' => 'ORD-TEST-'.uniqid(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'size' => null,
            'sweetness' => null,
            'qty' => $qty,
            'unit_price' => 15000,
        ]);

        return [$order, $ppp];
    }

    public function test_admin_can_mark_order_paid(): void
    {
        $this->actingAs(User::factory()->create());

        [$order] = $this->makeOrderWithReservedQuota();

        $response = $this->post("/admin/orders/{$order->id}/mark-paid");

        $response->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
        ]);
    }

    public function test_admin_can_update_order_status(): void
    {
        $this->actingAs(User::factory()->create());

        [$order] = $this->makeOrderWithReservedQuota();

        $response = $this->post("/admin/orders/{$order->id}/status", [
            'order_status' => 'diproses',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'diproses',
        ]);
    }

    public function test_update_status_rejects_cancelled_value(): void
    {
        $this->actingAs(User::factory()->create());

        [$order, $ppp] = $this->makeOrderWithReservedQuota(3);

        $response = $this->post("/admin/orders/{$order->id}/status", [
            'order_status' => 'cancelled',
        ]);

        $response->assertSessionHasErrors('order_status');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'new',
        ]);

        // Quota must remain reserved since the order was not actually cancelled.
        $this->assertSame(3, $ppp->fresh()->ordered_qty);
    }

    public function test_cancel_releases_quota(): void
    {
        $this->actingAs(User::factory()->create());

        [$order, $ppp] = $this->makeOrderWithReservedQuota(3);

        $response = $this->post("/admin/orders/{$order->id}/cancel");

        $response->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'cancelled',
        ]);

        $this->assertSame(0, $ppp->fresh()->ordered_qty);
    }

    public function test_cancelling_a_completed_order_does_not_release_quota(): void
    {
        $this->actingAs(User::factory()->create());

        [$order, $ppp] = $this->makeOrderWithReservedQuota(3);
        $order->update(['order_status' => 'selesai']);

        $response = $this->post("/admin/orders/{$order->id}/cancel");

        $response->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'selesai',
        ]);

        // Quota must remain reserved; a completed order cannot be cancelled.
        $this->assertSame(3, $ppp->fresh()->ordered_qty);
    }

    public function test_update_status_rejects_transition_away_from_cancelled(): void
    {
        $this->actingAs(User::factory()->create());

        [$order, $ppp] = $this->makeOrderWithReservedQuota(3);

        $this->post("/admin/orders/{$order->id}/cancel");
        $this->assertSame(0, $ppp->fresh()->ordered_qty);

        $response = $this->post("/admin/orders/{$order->id}/status", [
            'order_status' => 'diproses',
        ]);

        $response->assertSessionHasErrors('order_status');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'cancelled',
        ]);

        // Quota must stay released; the order must not come back to life.
        $this->assertSame(0, $ppp->fresh()->ordered_qty);
    }

    public function test_cancelling_already_cancelled_order_does_not_double_release_quota(): void
    {
        $this->actingAs(User::factory()->create());

        [$order, $ppp] = $this->makeOrderWithReservedQuota(3);

        $this->post("/admin/orders/{$order->id}/cancel");
        $this->assertSame(0, $ppp->fresh()->ordered_qty);

        // Second cancel attempt must be a no-op: ordered_qty must not go negative
        // or be decremented a second time.
        $this->post("/admin/orders/{$order->id}/cancel");

        $this->assertSame(0, $ppp->fresh()->ordered_qty);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'cancelled',
        ]);
    }
}
