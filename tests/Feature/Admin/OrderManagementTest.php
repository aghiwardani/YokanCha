<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemIngredient;
use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StocksMenu;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;
    use StocksMenu;

    private function makeOrderWithReservedStock(int $qty = 3): array
    {
        $period = PoPeriod::factory()->create();
        $product = Product::factory()->create();

        PoPeriodProduct::create([
            'po_period_id' => $period->id,
            'product_id' => $product->id,
        ]);

        // 200 ml of milk per bottle, already reserved for this order.
        $milk = $this->ingredient('Susu');
        $stock = $this->stock($period, $milk, 1000, $qty * 200);
        $this->recipe($product, '-', $milk, 200);

        $order = Order::create([
            'po_period_id' => $period->id,
            'customer_name' => 'Budi',
            'customer_whatsapp' => '08123456789',
            'payment_status' => 'unpaid',
            'order_status' => 'new',
            'order_number' => 'ORD-TEST-'.uniqid(),
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'size' => null,
            'sweetness' => null,
            'qty' => $qty,
            'unit_price' => 15000,
        ]);

        OrderItemIngredient::create([
            'order_item_id' => $item->id,
            'ingredient_id' => $milk->id,
            'amount' => $qty * 200,
        ]);

        return [$order, $stock];
    }

    public function test_admin_can_mark_order_paid(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        [$order] = $this->makeOrderWithReservedStock();

        $response = $this->post("/admin/orders/{$order->id}/mark-paid");

        $response->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
        ]);
    }

    public function test_admin_can_update_order_status(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        [$order] = $this->makeOrderWithReservedStock();

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
        $this->actingAs(User::factory()->admin()->create());

        [$order, $stock] = $this->makeOrderWithReservedStock(3);

        $response = $this->post("/admin/orders/{$order->id}/status", [
            'order_status' => 'cancelled',
        ]);

        $response->assertSessionHasErrors('order_status');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'new',
        ]);

        // Stock must remain reserved since the order was not actually cancelled.
        $this->assertEquals(600, $stock->fresh()->used);
    }

    public function test_cancel_returns_ingredients_to_stock(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        [$order, $stock] = $this->makeOrderWithReservedStock(3);

        $response = $this->post("/admin/orders/{$order->id}/cancel");

        $response->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'cancelled',
        ]);

        $this->assertEquals(0, $stock->fresh()->used);
    }

    public function test_cancelling_a_completed_order_does_not_release_stock(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        [$order, $stock] = $this->makeOrderWithReservedStock(3);
        $order->update(['order_status' => 'selesai']);

        $response = $this->post("/admin/orders/{$order->id}/cancel");

        $response->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'selesai',
        ]);

        // Stock must remain reserved; a completed order cannot be cancelled.
        $this->assertEquals(600, $stock->fresh()->used);
    }

    public function test_update_status_rejects_transition_away_from_cancelled(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        [$order, $stock] = $this->makeOrderWithReservedStock(3);

        $this->post("/admin/orders/{$order->id}/cancel");
        $this->assertEquals(0, $stock->fresh()->used);

        $response = $this->post("/admin/orders/{$order->id}/status", [
            'order_status' => 'diproses',
        ]);

        $response->assertSessionHasErrors('order_status');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'cancelled',
        ]);

        // Stock must stay released; the order must not come back to life.
        $this->assertEquals(0, $stock->fresh()->used);
    }

    public function test_cancel_returns_snapshot_after_recipe_edit(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        [$order, $stock] = $this->makeOrderWithReservedStock(2);

        // Recipe changes after the order was placed; the snapshot (2 x 200) wins.
        ProductRecipe::query()->update(['amount' => 300]);

        $this->post("/admin/orders/{$order->id}/cancel");

        $this->assertEquals(0, $stock->fresh()->used);
    }

    public function test_cancelling_already_cancelled_order_does_not_double_release_stock(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        [$order, $stock] = $this->makeOrderWithReservedStock(3);

        $this->post("/admin/orders/{$order->id}/cancel");
        $this->assertEquals(0, $stock->fresh()->used);

        // Second cancel attempt must be a no-op: used stock must not go negative
        // or be decremented a second time.
        $this->post("/admin/orders/{$order->id}/cancel");

        $this->assertEquals(0, $stock->fresh()->used);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'cancelled',
        ]);
    }
}
