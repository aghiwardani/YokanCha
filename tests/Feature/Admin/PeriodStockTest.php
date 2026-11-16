<?php

namespace Tests\Feature\Admin;

use App\Models\PoPeriod;
use App\Models\PoPeriodIngredient;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StocksMenu;
use Tests\TestCase;

class PeriodStockTest extends TestCase
{
    use RefreshDatabase, StocksMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_can_save_stock(): void
    {
        $period = PoPeriod::factory()->create();
        $milk = $this->ingredient('Susu');
        $sugar = $this->ingredient('Gula', 'g', true);
        $product = Product::factory()->create();

        $this->put("/admin/periods/{$period->id}/stock", [
            'stock' => [$milk->id => '500', $sugar->id => ''],
            'products' => [$product->id],
        ])->assertRedirect()->assertSessionHas('status', 'Stok bahan disimpan.');

        $this->assertDatabaseHas('po_period_ingredients', [
            'po_period_id' => $period->id, 'ingredient_id' => $milk->id, 'stock' => 500,
        ]);
        $this->assertDatabaseMissing('po_period_ingredients', ['ingredient_id' => $sugar->id]);
        $this->assertDatabaseHas('po_period_products', ['po_period_id' => $period->id, 'product_id' => $product->id]);
    }

    public function test_stock_typed_with_thousand_dots_and_decimal_comma_is_read_correctly(): void
    {
        $period = PoPeriod::factory()->create();
        $milk = $this->ingredient('Susu');
        $matcha = $this->ingredient('Matcha', 'g');

        $this->put("/admin/periods/{$period->id}/stock", [
            'stock' => [$milk->id => '2.000', $matcha->id => '150,5'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals(2000, PoPeriodIngredient::where('ingredient_id', $milk->id)->value('stock'));
        $this->assertEquals(150.5, PoPeriodIngredient::where('ingredient_id', $matcha->id)->value('stock'));
    }

    public function test_stock_page_marks_amount_fields_for_thousand_separators(): void
    {
        $period = PoPeriod::factory()->create();
        $milk = $this->ingredient('Susu');

        $this->get("/admin/periods/{$period->id}/stock")
            ->assertOk()
            ->assertSee('name="stock['.$milk->id.']"', false)
            ->assertSee('data-number', false);
    }

    public function test_stock_above_column_limit_is_rejected(): void
    {
        $period = PoPeriod::factory()->create();
        $milk = $this->ingredient('Susu');

        $this->put("/admin/periods/{$period->id}/stock", [
            'stock' => [$milk->id => '1000000000'],
        ])->assertSessionHasErrors('stock.'.$milk->id);

        $this->assertDatabaseCount('po_period_ingredients', 0);
    }

    public function test_new_period_includes_all_products(): void
    {
        $a = Product::factory()->create();
        $b = Product::factory()->create();

        $this->post('/admin/periods', ['name' => 'PO Baru'])->assertRedirect('/admin/periods');

        $period = PoPeriod::where('name', 'PO Baru')->firstOrFail();
        $this->assertDatabaseHas('po_period_products', ['po_period_id' => $period->id, 'product_id' => $a->id]);
        $this->assertDatabaseHas('po_period_products', ['po_period_id' => $period->id, 'product_id' => $b->id]);
    }

    public function test_topping_up_mid_period_raises_availability(): void
    {
        $period = PoPeriod::factory()->create();
        $milk = $this->ingredient('Susu');
        $product = Product::factory()->create();
        $this->recipe($product, '250ml', $milk, 100);
        $ppp = PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $product->id]);
        $this->stock($period, $milk, 500, 300);

        $service = app(StockService::class);
        $this->assertSame(2, $service->available($ppp, '250ml', 'Normal'));

        $this->put("/admin/periods/{$period->id}/stock", [
            'stock' => [$milk->id => '800'],
            'products' => [$product->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame(5, $service->available($ppp->fresh(), '250ml', 'Normal'));
        $this->assertEquals(300, PoPeriodIngredient::first()->used);
    }

    public function test_cannot_lower_stock_below_used(): void
    {
        $period = PoPeriod::factory()->create();
        $milk = $this->ingredient('Susu');
        $this->stock($period, $milk, 500, 300);

        $this->put("/admin/periods/{$period->id}/stock", ['stock' => [$milk->id => '200']])
            ->assertSessionHasErrors(["stock.{$milk->id}" => 'Stok Susu tidak boleh di bawah yang sudah terpakai (300 ml).']);

        $this->put("/admin/periods/{$period->id}/stock", ['stock' => [$milk->id => '']])
            ->assertSessionHasErrors("stock.{$milk->id}");

        $this->assertEquals(500, PoPeriodIngredient::first()->stock);
    }

    public function test_prefill_uses_previous_period_remaining(): void
    {
        $prev = PoPeriod::factory()->create(['created_at' => now()->subDays(10)]);
        $period = PoPeriod::factory()->create(['created_at' => now()]);
        $milk = $this->ingredient('Susu');
        $this->stock($prev, $milk, 100, 70);

        $response = $this->get("/admin/periods/{$period->id}/stock?prefill=previous");

        $response->assertOk()->assertSee('Isi dari sisa periode lalu');
        $response->assertSee('name="stock['.$milk->id.']"', false);
        $response->assertSee('value="30"', false);
        $this->assertDatabaseMissing('po_period_ingredients', ['po_period_id' => $period->id]);
    }

    public function test_unchecked_product_leaves_batch(): void
    {
        $period = PoPeriod::factory()->create();
        $keep = Product::factory()->create();
        $drop = Product::factory()->create();
        PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $keep->id]);
        PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $drop->id]);

        $this->put("/admin/periods/{$period->id}/stock", ['products' => [$keep->id]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('po_period_products', ['po_period_id' => $period->id, 'product_id' => $keep->id]);
        $this->assertDatabaseMissing('po_period_products', ['po_period_id' => $period->id, 'product_id' => $drop->id]);
    }
}
