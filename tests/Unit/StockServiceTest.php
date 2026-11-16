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
            'po_period_id' => $this->period->id, 'product_id' => $product->id, 'quota' => 0,
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

    public function test_less_sugar_uses_its_own_sugar_amount(): void
    {
        [$p, $ppp] = $this->product();
        $gula = $this->ingredient('Gula', 'g', true);
        $this->stock($this->period, $gula, 100);
        $this->recipe($p, 'Regular', $gula, 20, 10);

        $this->assertSame(5, $this->svc()->available($ppp, 'Regular', 'Normal'));
        $this->assertSame(10, $this->svc()->available($ppp, 'Regular', 'Less Sugar'));
    }

    public function test_less_sugar_falls_back_to_normal_amount_when_blank(): void
    {
        [$p, $ppp] = $this->product();
        $gula = $this->ingredient('Gula', 'g', true);
        $this->stock($this->period, $gula, 100);
        $this->recipe($p, 'Regular', $gula, 20, null);

        $this->assertSame(5, $this->svc()->available($ppp, 'Regular', ' less sugar '));
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
