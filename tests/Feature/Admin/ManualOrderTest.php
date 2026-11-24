<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\PoPeriod;
use App\Models\PoPeriodIngredient;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StocksMenu;
use Tests\TestCase;

class ManualOrderTest extends TestCase
{
    use RefreshDatabase;
    use StocksMenu;

    private PoPeriod $period;

    private PoPeriodProduct $ppp;

    private PoPeriodIngredient $milkStock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());

        // A batch that already closed, with 600 ml of milk left.
        $this->period = PoPeriod::create(['name' => 'PO Kemarin', 'status' => 'closed', 'closes_at' => now()->subHour()]);
        $product = Product::create([
            'name' => 'Maple Signature', 'base_price' => 32000,
            'size_options' => ['250 ml', '1 liter'], 'size_prices' => ['250 ml' => 28000, '1 liter' => 105000],
            'sweetness_options' => ['Normal', 'Less Sugar'],
        ]);
        $milk = $this->ingredient('Fresh Milk');
        $this->milkStock = $this->stock($this->period, $milk, 600);
        $this->recipe($product, '250 ml', $milk, 200);
        $this->recipe($product, '1 liter', $milk, 800);
        $this->ppp = PoPeriodProduct::create(['po_period_id' => $this->period->id, 'product_id' => $product->id]);
    }

    private function payload(array $lines, array $extra = []): array
    {
        return $extra + [
            'po_period_id' => $this->period->id,
            'customer_name' => 'Pak Budi',
            'customer_whatsapp' => '081234567890',
            'payment_status' => 'unpaid',
            'order_status' => 'new',
            'lines' => $lines,
        ];
    }

    public function test_form_lists_the_menu_of_a_closed_period(): void
    {
        $this->get(route('admin.orders.create', ['period' => $this->period->id]))
            ->assertOk()
            ->assertSee('Tambah pesanan')
            ->assertSee('PO Kemarin (tutup)')
            ->assertSee('Maple Signature')
            ->assertSee('Tetap simpan walau stok bahan kurang');
    }

    public function test_admin_adds_an_order_to_a_closed_period_and_stock_is_used(): void
    {
        $response = $this->post(route('admin.orders.store'), $this->payload([
            ['ppp' => $this->ppp->id, 'size' => '250 ml', 'sweetness' => 'Less Sugar', 'qty' => '2', 'price' => ''],
            ['ppp' => '', 'size' => '', 'sweetness' => '', 'qty' => '', 'price' => ''], // blank row
        ], ['payment_status' => 'paid']));

        $order = Order::firstOrFail();
        $response->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('status', "Pesanan Pak Budi ditambahkan ({$order->order_number}).");

        $this->assertSame('admin', $order->source);
        $this->assertSame('paid', $order->payment_status);
        $this->assertStringStartsWith('YC-'.$this->period->id.'-', $order->order_number);
        $item = $order->items()->first();
        $this->assertSame(2, $item->qty);
        $this->assertSame(28000, $item->unit_price); // price of the size
        $this->assertEquals(400, $this->milkStock->fresh()->used);
        $this->assertEquals(400, $item->ingredientUsages()->sum('amount'));

        $this->get(route('admin.orders'))->assertSee('Manual');
    }

    public function test_price_can_be_changed_for_a_discount_or_free_bottle(): void
    {
        $this->post(route('admin.orders.store'), $this->payload([
            ['ppp' => $this->ppp->id, 'size' => '250 ml', 'sweetness' => 'Normal', 'qty' => '1', 'price' => '0'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(0, Order::first()->items()->first()->unit_price);
    }

    public function test_short_stock_is_refused_unless_allowed(): void
    {
        // 1 liter needs 800 ml, only 600 left.
        $line = [['ppp' => $this->ppp->id, 'size' => '1 liter', 'sweetness' => 'Normal', 'qty' => '1', 'price' => '']];

        $this->post(route('admin.orders.store'), $this->payload($line))
            ->assertSessionHasErrors('allow_short');
        $this->assertSame(0, Order::count());
        $this->assertEquals(0, $this->milkStock->fresh()->used);

        $this->post(route('admin.orders.store'), $this->payload($line, ['allow_short' => '1']))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Order::count());
        $this->assertEquals(800, $this->milkStock->fresh()->used);
        $this->assertLessThan(0, $this->milkStock->fresh()->remaining());
    }

    public function test_rows_are_checked(): void
    {
        $other = PoPeriod::create(['name' => 'Lain', 'status' => 'open']);
        $foreign = PoPeriodProduct::create(['po_period_id' => $other->id, 'product_id' => $this->ppp->product_id]);

        $this->post(route('admin.orders.store'), $this->payload([
            ['ppp' => $foreign->id, 'size' => '250 ml', 'sweetness' => 'Normal', 'qty' => '1'],
            ['ppp' => $this->ppp->id, 'size' => '2 liter', 'sweetness' => 'Normal', 'qty' => '1'],
        ]))->assertSessionHasErrors(['lines.0.ppp', 'lines.1.size']);

        $this->post(route('admin.orders.store'), $this->payload([
            ['ppp' => '', 'size' => '', 'sweetness' => '', 'qty' => ''],
        ]))->assertSessionHasErrors('lines');

        $this->post(route('admin.orders.store'), $this->payload([], ['customer_whatsapp' => 'abc']))
            ->assertSessionHasErrors(['customer_whatsapp', 'lines']);

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_orders_are_marked_as_web(): void
    {
        $order = Order::create([
            'po_period_id' => $this->period->id, 'customer_name' => 'X', 'customer_whatsapp' => '0812',
            'order_number' => Order::newNumber($this->period->id),
        ]);

        $this->assertSame('web', $order->fresh()->source);
    }
}
