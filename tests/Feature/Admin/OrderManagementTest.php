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

    public function test_cancel_message_says_stock_returned(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        [$order] = $this->makeOrderWithReservedStock(3);

        $this->post("/admin/orders/{$order->id}/cancel")
            ->assertSessionHas('status', 'Pesanan dibatalkan dan bahan dikembalikan ke stok.');
    }

    public function test_cancel_message_for_order_without_snapshots(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        [$order, $stock] = $this->makeOrderWithReservedStock(3);
        OrderItemIngredient::query()->delete();

        $this->post("/admin/orders/{$order->id}/cancel")
            ->assertSessionHas('status', 'Pesanan dibatalkan. Pesanan ini dibuat sebelum stok bahan dicatat, jadi tidak ada bahan yang dikembalikan.');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status' => 'cancelled']);
        $this->assertEquals(600, $stock->fresh()->used);
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

    private function listOrder(PoPeriod $period, Product $product, string $name, string $payment, string $status, int $qty, int $price): Order
    {
        $order = Order::create([
            'po_period_id' => $period->id,
            'customer_name' => $name,
            'customer_whatsapp' => '081234567890',
            'payment_status' => $payment,
            'order_status' => $status,
            'order_number' => 'YC-'.uniqid(),
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'size' => '250 ml', 'sweetness' => 'Less Sugar', 'qty' => $qty, 'unit_price' => $price,
        ]);

        return $order;
    }

    public function test_order_list_shows_summary_items_and_whatsapp_link(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $period = PoPeriod::factory()->create();
        $product = Product::factory()->create(['name' => 'Maple Signature']);

        $this->listOrder($period, $product, 'Nui', 'paid', 'new', 2, 32000);
        $this->listOrder($period, $product, 'Santi', 'unpaid', 'new', 1, 32000);
        $this->listOrder($period, $product, 'Batal', 'unpaid', 'cancelled', 5, 32000);

        $this->get('/admin/orders')
            ->assertOk()
            ->assertSeeInOrder(['2×', 'Maple Signature'])
            ->assertSee('(250 ml, Less Sugar)', false)
            ->assertSee('Rp96.000')   // total penjualan, tanpa yang dibatalkan
            ->assertSee('Rp64.000')   // sudah dibayar
            ->assertSee('Rp32.000')   // belum dibayar
            ->assertSee('3 botol')
            ->assertSee('https://wa.me/6281234567890', false);
    }

    public function test_order_list_filters_by_payment_status_and_search(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $period = PoPeriod::factory()->create();
        $product = Product::factory()->create();

        $this->listOrder($period, $product, 'Nui Lunas', 'paid', 'new', 1, 32000);
        $this->listOrder($period, $product, 'Santi Nunggak', 'unpaid', 'diproses', 1, 32000);

        $this->get('/admin/orders?payment=unpaid')
            ->assertSee('Santi Nunggak')->assertDontSee('Nui Lunas');

        $this->get('/admin/orders?status=new')
            ->assertSee('Nui Lunas')->assertDontSee('Santi Nunggak');

        $this->get('/admin/orders?q=santi')
            ->assertSee('Santi Nunggak')->assertDontSee('Nui Lunas');
    }

    public function test_list_changes_payment_and_status_and_returns_to_the_same_card(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $period = PoPeriod::factory()->create();
        $product = Product::factory()->create();
        $order = $this->listOrder($period, $product, 'Nui', 'unpaid', 'new', 1, 32000);
        $list = route('admin.orders', ['po_period_id' => $period->id, 'payment' => 'unpaid']);

        $this->from($list)->post(route('admin.orders.mark-paid', $order), ['from' => 'list'])
            ->assertRedirect($list.'#order-'.$order->id)
            ->assertSessionHas('status', 'Pesanan Nui ditandai sudah bayar.');
        $this->assertSame('paid', $order->fresh()->payment_status);

        $this->from($list)->post(route('admin.orders.status', $order), ['from' => 'list', 'order_status' => 'siap_diambil'])
            ->assertRedirect($list.'#order-'.$order->id)
            ->assertSessionHas('status', 'Status pesanan Nui diubah ke Siap diambil.');
        $this->assertSame('siap_diambil', $order->fresh()->order_status);

        $this->from($list)->post(route('admin.orders.mark-unpaid', $order), ['from' => 'list'])
            ->assertRedirect($list.'#order-'.$order->id);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_list_shows_quick_controls_except_for_cancelled_orders(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $period = PoPeriod::factory()->create();
        $product = Product::factory()->create();
        $live = $this->listOrder($period, $product, 'Nui', 'unpaid', 'new', 1, 32000);
        $gone = $this->listOrder($period, $product, 'Batal', 'unpaid', 'cancelled', 1, 32000);

        $this->get('/admin/orders')
            ->assertSee(route('admin.orders.mark-paid', $live), false)
            ->assertSee(route('admin.orders.status', $live), false)
            ->assertDontSee(route('admin.orders.mark-paid', $gone), false)
            ->assertDontSee(route('admin.orders.status', $gone), false);
    }

    public function test_change_from_detail_page_still_returns_to_detail(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        [$order] = $this->makeOrderWithReservedStock();

        $this->post(route('admin.orders.mark-paid', $order))->assertRedirect(route('admin.orders.show', $order));
    }

    public function test_redirect_never_leaves_the_order_list(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        [$order] = $this->makeOrderWithReservedStock();

        $this->from('https://evil.example/x')->post(route('admin.orders.mark-paid', $order), ['from' => 'list'])
            ->assertRedirect(route('admin.orders').'#order-'.$order->id);
    }

    public function test_quick_change_in_background_answers_with_json(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        [$order] = $this->makeOrderWithReservedStock();

        $this->postJson(route('admin.orders.status', $order), ['from' => 'list', 'order_status' => 'diproses'])
            ->assertOk()
            ->assertJson(['message' => 'Status pesanan Budi diubah ke Diproses.']);
        $this->assertSame('diproses', $order->fresh()->order_status);

        $this->get('/admin/orders')->assertSee('data-orders-root', false)->assertSee('data-quick', false);
    }
}
