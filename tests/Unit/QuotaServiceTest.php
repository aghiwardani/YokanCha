<?php

namespace Tests\Unit;

use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use App\Services\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makePpp(int $quota, int $orderedQty): PoPeriodProduct
    {
        $period = PoPeriod::create([
            'name' => 'Test Period',
            'status' => 'open',
        ]);

        $product = Product::create([
            'category' => 'minuman',
            'name' => 'Test Product',
            'base_price' => 10000,
        ]);

        return PoPeriodProduct::create([
            'po_period_id' => $period->id,
            'product_id' => $product->id,
            'quota' => $quota,
            'ordered_qty' => $orderedQty,
        ]);
    }

    public function test_reserve_succeeds_and_decrements_remaining(): void
    {
        $ppp = $this->makePpp(10, 0);

        $result = (new QuotaService())->reserve($ppp, 3);

        $this->assertTrue($result);
        $this->assertSame(7, $ppp->fresh()->remaining());
    }

    public function test_reserve_fails_when_insufficient_remaining(): void
    {
        $ppp = $this->makePpp(2, 0);

        $result = (new QuotaService())->reserve($ppp, 3);

        $this->assertFalse($result);
        $this->assertSame(0, $ppp->fresh()->ordered_qty);
    }

    public function test_release_decrements_ordered_qty_not_below_zero(): void
    {
        $ppp = $this->makePpp(10, 2);

        (new QuotaService())->release($ppp, 5);

        $this->assertSame(0, $ppp->fresh()->ordered_qty);
    }
}
