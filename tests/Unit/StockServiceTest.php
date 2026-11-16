<?php

namespace Tests\Unit;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemIngredient;
use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StocksMenu;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase, StocksMenu;

    private PoPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->period = PoPeriod::create(['name' => 'P', 'status' => 'open']);
    }

    private function svc(): StockService
    {
        return new StockService();
    }

    private function product(array $sizes = ['Regular'], array $sweet = ['Normal', 'Less Sugar'], string $name = 'Latte'): array
    {
        $product = Product::create([
            'category' => 'minuman', 'name' => $name, 'base_price' => 10000,
            'size_options' => $sizes, 'sweetness_options' => $sweet,
        ]);
        $ppp = PoPeriodProduct::create([
            'po_period_id' => $this->period->id, 'product_id' => $product->id,
        ]);

        return [$product, $ppp];
    }

    public function test_scarcest_ingredient_decides(): void
    {
        [$p, $ppp] = $this->product();
        $milk = $this->ingredient('Milk');
        $matcha = $this->ingredient('Matcha', 'g');
        $this->stock($this->period, $milk, 2000, 200);
        $this->stock($this->period, $matcha, 30, 5);
        $this->recipe($p, 'Regular', $milk, 180);
        $this->recipe($p, 'Regular', $matcha, 4);

        // milk 1800/180 = 10, matcha 25/4 = 6
        $this->assertSame(6, $this->svc()->available($ppp, 'Regular', 'Normal'));
    }

    public function test_sugar_follows_the_sweetness_level_percent(): void
    {
        [$p, $ppp] = $this->product(['Regular'], ['Less', 'Normal', 'Extra']);
        $this->sweetness('Less', 50);
        $this->sweetness('Normal', 100);
        $this->sweetness('Extra', 150);
        $gula = $this->ingredient('Gula', 'g', true);
        $this->stock($this->period, $gula, 300);
        $this->recipe($p, 'Regular', $gula, 20);

        $this->assertSame(30, $this->svc()->available($ppp, 'Regular', 'Less'));    // 10 g each
        $this->assertSame(15, $this->svc()->available($ppp, 'Regular', 'Normal'));  // 20 g each
        $this->assertSame(10, $this->svc()->available($ppp, 'Regular', 'Extra'));   // 30 g each
    }

    public function test_every_sugar_ingredient_is_scaled_but_other_ingredients_are_not(): void
    {
        [$p] = $this->product();
        $this->sweetness('Less', 50);
        $milk = $this->ingredient('Milk');
        $maple = $this->ingredient('Maple', 'ml', true);
        $simple = $this->ingredient('Simple', 'ml', true);
        $this->recipe($p, 'Regular', $milk, 200);
        $this->recipe($p, 'Regular', $maple, 20);
        $this->recipe($p, 'Regular', $simple, 7);

        $per = $this->svc()->perBottle($p, 'Regular', 'Less');

        $this->assertEquals(200.0, $per[$milk->id]);
        $this->assertEquals(10.0, $per[$maple->id]);
        $this->assertEquals(3.5, $per[$simple->id]);
    }

    public function test_no_sugar_level_uses_none_of_the_sweetener(): void
    {
        [$p, $ppp] = $this->product(['Regular'], ['Tanpa gula', 'Normal']);
        $this->sweetness('Tanpa gula', 0);
        $milk = $this->ingredient('Milk');
        $gula = $this->ingredient('Gula', 'g', true);
        $this->stock($this->period, $milk, 1000);
        $this->stock($this->period, $gula, 0);
        $this->recipe($p, 'Regular', $milk, 200);
        $this->recipe($p, 'Regular', $gula, 20);

        $this->assertSame([$milk->id => 200.0], $this->svc()->perBottle($p, 'Regular', 'Tanpa gula'));
        $this->assertSame(5, $this->svc()->available($ppp, 'Regular', 'Tanpa gula'));
        $this->assertSame(0, $this->svc()->available($ppp, 'Regular', 'Normal'));
    }

    public function test_level_missing_from_master_falls_back_to_a_guess_from_its_name(): void
    {
        [$p, $ppp] = $this->product();
        $gula = $this->ingredient('Gula', 'g', true);
        $this->stock($this->period, $gula, 100);
        $this->recipe($p, 'Regular', $gula, 20);

        $this->assertSame(10, $this->svc()->available($ppp, 'Regular', ' less sugar '));  // 50%
        $this->assertSame(5, $this->svc()->available($ppp, 'Regular', 'Normal'));         // 100%
    }

    public function test_decimal_amounts_do_not_lose_a_bottle(): void
    {
        [$p, $ppp] = $this->product();
        $x = $this->ingredient('X', 'g');
        $this->stock($this->period, $x, 100);
        $this->recipe($p, 'Regular', $x, 2.5);

        $this->assertSame(40, $this->svc()->available($ppp, 'Regular', 'Normal'));
    }

    public function test_missing_stock_row_means_unavailable(): void
    {
        [$p, $ppp] = $this->product();
        $milk = $this->ingredient('Milk');
        $this->recipe($p, 'Regular', $milk, 200);

        $this->assertSame(0, $this->svc()->available($ppp, 'Regular', 'Normal'));
    }

    public function test_no_recipe_for_size_means_unavailable(): void
    {
        [$p, $ppp] = $this->product();
        $milk = $this->ingredient('Milk');
        $this->stock($this->period, $milk, 2000);
        $this->recipe($p, 'Regular', $milk, 200);

        $this->assertSame(0, $this->svc()->available($ppp, 'Large', 'Normal'));
    }

    public function test_already_needed_reduces_available(): void
    {
        [$p, $ppp] = $this->product();
        $milk = $this->ingredient('Milk');
        $this->stock($this->period, $milk, 2000);
        $this->recipe($p, 'Regular', $milk, 200);

        $this->assertSame(5, $this->svc()->available($ppp, 'Regular', 'Normal', [$milk->id => 1000.0]));
    }

    public function test_product_without_sizes_uses_dash_key(): void
    {
        [$p, $ppp] = $this->product([], []);
        $milk = $this->ingredient('Milk');
        $this->stock($this->period, $milk, 1000);
        $this->recipe($p, '-', $milk, 250);

        $this->assertSame(4, $this->svc()->maxAvailable($ppp));
    }

    public function test_max_available_takes_best_variant(): void
    {
        [$p, $ppp] = $this->product(['Regular', 'Large']);
        $milk = $this->ingredient('Milk');
        $this->stock($this->period, $milk, 1000);
        $this->recipe($p, 'Regular', $milk, 100);
        $this->recipe($p, 'Large', $milk, 500);

        $this->assertSame(10, $this->svc()->maxAvailable($ppp));
    }

    public function test_reserve_increments_used_and_returns_usage(): void
    {
        [$p, $ppp] = $this->product();
        $milk = $this->ingredient('Milk');
        $row = $this->stock($this->period, $milk, 2000, 100);
        $this->recipe($p, 'Regular', $milk, 200);

        $usage = $this->svc()->reserve($this->period->id, [
            ['po_period_product' => $ppp, 'size' => 'Regular', 'sweetness' => 'Normal', 'qty' => 3],
        ]);

        $this->assertEquals([0 => [$milk->id => 600.0]], $usage);
        $this->assertEquals(700.0, (float) $row->fresh()->used);
    }

    public function test_reserve_throws_and_changes_nothing_when_short(): void
    {
        [$p, $ppp] = $this->product(['Regular'], ['Normal'], 'Latte');
        [$p2, $ppp2] = $this->product(['Regular'], ['Normal'], 'Mocha');
        $milk = $this->ingredient('Milk');
        $row = $this->stock($this->period, $milk, 500, 0);
        $this->recipe($p, 'Regular', $milk, 200);
        $this->recipe($p2, 'Regular', $milk, 200);

        try {
            $this->svc()->reserve($this->period->id, [
                ['po_period_product' => $ppp, 'size' => 'Regular', 'sweetness' => 'Normal', 'qty' => 2],
                ['po_period_product' => $ppp2, 'size' => 'Regular', 'sweetness' => 'Normal', 'qty' => 1],
            ]);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertSame('Mocha', $e->productName);
        }

        $this->assertEquals(0.0, (float) $row->fresh()->used);
    }

    public function test_reserve_throws_when_line_has_no_recipe(): void
    {
        [$p, $ppp] = $this->product(['Regular'], ['Normal'], 'Latte');
        $milk = $this->ingredient('Milk');
        $row = $this->stock($this->period, $milk, 2000, 0);

        try {
            $this->svc()->reserve($this->period->id, [
                ['po_period_product' => $ppp, 'size' => 'Regular', 'sweetness' => 'Normal', 'qty' => 1],
            ]);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertSame('Latte', $e->productName);
        }

        $this->assertEquals(0.0, (float) $row->fresh()->used);
    }

    public function test_reserve_throws_when_recipe_ingredient_has_no_stock_row(): void
    {
        [$p, $ppp] = $this->product(['Regular'], ['Normal'], 'Latte');
        $milk = $this->ingredient('Milk');
        $matcha = $this->ingredient('Matcha', 'g');
        $row = $this->stock($this->period, $milk, 2000, 0);
        $this->recipe($p, 'Regular', $milk, 100);
        $this->recipe($p, 'Regular', $matcha, 4);

        try {
            $this->svc()->reserve($this->period->id, [
                ['po_period_product' => $ppp, 'size' => 'Regular', 'sweetness' => 'Normal', 'qty' => 1],
            ]);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertSame('Latte', $e->productName);
        }

        $this->assertEquals(0.0, (float) $row->fresh()->used);
    }

    public function test_reserve_returns_usage_keyed_by_each_line_index(): void
    {
        [$p, $ppp] = $this->product(['Regular'], ['Normal'], 'Latte');
        [$p2, $ppp2] = $this->product(['Regular'], ['Normal'], 'Mocha');
        $milk = $this->ingredient('Milk');
        $cocoa = $this->ingredient('Cocoa', 'g');
        $this->stock($this->period, $milk, 2000, 0);
        $this->stock($this->period, $cocoa, 100, 0);
        $this->recipe($p, 'Regular', $milk, 200);
        $this->recipe($p2, 'Regular', $milk, 100);
        $this->recipe($p2, 'Regular', $cocoa, 5);

        $usage = $this->svc()->reserve($this->period->id, [
            ['po_period_product' => $ppp, 'size' => 'Regular', 'sweetness' => 'Normal', 'qty' => 2],
            ['po_period_product' => $ppp2, 'size' => 'Regular', 'sweetness' => 'Normal', 'qty' => 3],
        ]);

        $this->assertEquals([
            0 => [$milk->id => 400.0],
            1 => [$milk->id => 300.0, $cocoa->id => 15.0],
        ], $usage);
    }

    public function test_reserve_handles_decimal_amount_times_qty(): void
    {
        [$p, $ppp] = $this->product();
        $matcha = $this->ingredient('Matcha', 'g');
        $row = $this->stock($this->period, $matcha, 100, 0);
        $this->recipe($p, 'Regular', $matcha, 2.5);

        $usage = $this->svc()->reserve($this->period->id, [
            ['po_period_product' => $ppp, 'size' => 'Regular', 'sweetness' => 'Normal', 'qty' => 3],
        ]);

        $this->assertEquals([0 => [$matcha->id => 7.5]], $usage);
        $this->assertEquals(7.5, (float) $row->fresh()->used);
    }

    public function test_release_returns_whether_anything_was_released(): void
    {
        [$p] = $this->product();
        $milk = $this->ingredient('Milk');
        $this->stock($this->period, $milk, 2000, 300);
        $order = Order::create([
            'po_period_id' => $this->period->id, 'customer_name' => 'Budi',
            'customer_whatsapp' => '08123456789', 'payment_status' => 'unpaid',
            'order_status' => 'new', 'order_number' => 'ORD-TEST-2',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'product_id' => $p->id, 'size' => 'Regular',
            'sweetness' => 'Normal', 'qty' => 1, 'unit_price' => 1,
        ]);

        $this->assertFalse($this->svc()->release($order));

        OrderItemIngredient::create(['order_item_id' => $item->id, 'ingredient_id' => $milk->id, 'amount' => 200]);
        $this->assertTrue($this->svc()->release($order));
    }

    public function test_release_returns_snapshot_not_below_zero(): void
    {
        [$p] = $this->product();
        $milk = $this->ingredient('Milk');
        $row = $this->stock($this->period, $milk, 2000, 300);
        $order = Order::create([
            'po_period_id' => $this->period->id,
            'customer_name' => 'Budi',
            'customer_whatsapp' => '08123456789',
            'payment_status' => 'unpaid',
            'order_status' => 'new',
            'order_number' => 'ORD-TEST-1',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'product_id' => $p->id, 'size' => 'Regular',
            'sweetness' => 'Normal', 'qty' => 1, 'unit_price' => 1,
        ]);
        OrderItemIngredient::create(['order_item_id' => $item->id, 'ingredient_id' => $milk->id, 'amount' => 200]);

        $this->svc()->release($order);
        $this->assertEquals(100.0, (float) $row->fresh()->used);

        $this->svc()->release($order);
        $this->assertEquals(0.0, (float) $row->fresh()->used);
    }
}
