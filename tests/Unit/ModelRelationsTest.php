<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_po_period_product_remaining_is_quota_minus_ordered(): void
    {
        $poPeriod = PoPeriod::create([
            'name' => 'PO Minggu 1',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'status' => 'open',
        ]);

        $product = Product::create([
            'category' => 'minuman',
            'name' => 'Es Teh',
            'description' => 'Teh manis dingin',
            'base_price' => 10000,
            'size_options' => ['S', 'M', 'L'],
            'sweetness_options' => ['normal', 'less sugar'],
        ]);

        $poPeriodProduct = PoPeriodProduct::create([
            'po_period_id' => $poPeriod->id,
            'product_id' => $product->id,
            'quota' => 50,
            'ordered_qty' => 12,
        ]);

        $this->assertSame(38, $poPeriodProduct->remaining());
    }

    public function test_po_period_is_open_only_within_window_and_status_open(): void
    {
        $open = PoPeriod::create([
            'name' => 'Open period',
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHour(),
            'status' => 'open',
        ]);
        $this->assertTrue($open->isOpen());

        $notOpenStatus = PoPeriod::create([
            'name' => 'Draft period',
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHour(),
            'status' => 'draft',
        ]);
        $this->assertFalse($notOpenStatus->isOpen());

        $notYetOpened = PoPeriod::create([
            'name' => 'Future period',
            'opens_at' => now()->addHour(),
            'closes_at' => now()->addDay(),
            'status' => 'open',
        ]);
        $this->assertFalse($notYetOpened->isOpen());

        $alreadyClosed = PoPeriod::create([
            'name' => 'Past period',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->subHour(),
            'status' => 'open',
        ]);
        $this->assertFalse($alreadyClosed->isOpen());

        $noWindow = PoPeriod::create([
            'name' => 'No window period',
            'opens_at' => null,
            'closes_at' => null,
            'status' => 'open',
        ]);
        $this->assertTrue($noWindow->isOpen());
    }

    public function test_order_has_many_order_items(): void
    {
        $poPeriod = PoPeriod::create([
            'name' => 'PO Minggu 1',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'status' => 'open',
        ]);

        $product = Product::create([
            'category' => 'minuman',
            'name' => 'Es Teh',
            'description' => 'Teh manis dingin',
            'base_price' => 10000,
            'size_options' => ['S', 'M', 'L'],
            'sweetness_options' => ['normal', 'less sugar'],
        ]);

        $order = Order::create([
            'po_period_id' => $poPeriod->id,
            'customer_name' => 'Budi',
            'customer_whatsapp' => '081234567890',
            'payment_status' => 'unpaid',
            'order_status' => 'new',
            'order_number' => 'ORD-0001',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'size' => 'M',
            'sweetness' => 'normal',
            'qty' => 2,
            'unit_price' => 10000,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'size' => 'L',
            'sweetness' => 'less sugar',
            'qty' => 1,
            'unit_price' => 12000,
        ]);

        $this->assertInstanceOf(OrderItem::class, $order->items->first());
        $this->assertCount(2, $order->items);
        $this->assertTrue($poPeriod->poPeriodProducts()->exists() === false);
        $this->assertTrue($poPeriod->orders->contains($order));
    }
}
