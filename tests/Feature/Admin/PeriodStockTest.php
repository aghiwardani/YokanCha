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

    public function test_purchase_price_is_saved_with_the_stock(): void
    {
        $period = PoPeriod::factory()->create();
        $milk = $this->ingredient('Susu');

        $this->put("/admin/periods/{$period->id}/stock", [
            'stock' => [$milk->id => '2.000'],
            'cost' => [$milk->id => '40.000'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $row = PoPeriodIngredient::where('ingredient_id', $milk->id)->first();
        $this->assertSame(40000, $row->cost);
        $this->assertEquals(20.0, $row->unitCost());

        $this->get("/admin/periods/{$period->id}/stock")
            ->assertSee('value="40000"', false)
            ->assertSee('Rp20 per ml');
    }

    public function test_saving_the_page_again_keeps_the_price_intact(): void
    {
        // Bug: 80.000 was shown pre-formatted, read back by the page script
        // as 80 and saved as 80 on the next save.
        $period = PoPeriod::factory()->create();
        $milk = $this->ingredient('Susu');
        $period->costs()->create(['name' => 'Es batu', 'type' => 'per_batch', 'amount' => 25000]);
        $this->put("/admin/periods/{$period->id}/stock", ['stock' => [$milk->id => '2000'], 'cost' => [$milk->id => '80.000']]);

        $html = $this->get("/admin/periods/{$period->id}/stock")->getContent();

        $this->assertStringContainsString('name="cost['.$milk->id.']"', $html);
        $this->assertMatchesRegularExpression('/name="cost\['.$milk->id.'\]"\s+value="80000"/', $html);
        $this->assertMatchesRegularExpression('/name="extra\[0\]\[amount\]" value="25000"/', $html);
        $this->assertSame(80000, PoPeriodIngredient::where('ingredient_id', $milk->id)->value('cost'));
    }

    public function test_saving_without_price_field_keeps_the_saved_price(): void
    {
        $period = PoPeriod::factory()->create();
        $milk = $this->ingredient('Susu');
        $this->put("/admin/periods/{$period->id}/stock", ['stock' => [$milk->id => '2000'], 'cost' => [$milk->id => '40000']]);

        $this->put("/admin/periods/{$period->id}/stock", ['stock' => [$milk->id => '3000']]);

        $this->assertSame(40000, PoPeriodIngredient::where('ingredient_id', $milk->id)->value('cost'));
    }

    public function test_prefill_from_previous_period_carries_the_value_of_leftovers(): void
    {
        $previous = PoPeriod::factory()->create(['created_at' => now()->subWeek()]);
        $milk = $this->ingredient('Susu');
        PoPeriodIngredient::create([
            'po_period_id' => $previous->id, 'ingredient_id' => $milk->id, 'stock' => 2000, 'used' => 1500, 'cost' => 40000,
        ]);
        $period = PoPeriod::factory()->create();

        // 500 ml left at Rp20 per ml.
        $this->get("/admin/periods/{$period->id}/stock?prefill=previous")
            ->assertSee('value="500"', false)
            ->assertSee('value="10000"', false);
    }

    public function test_other_costs_are_saved_and_replace_the_old_list(): void
    {
        $period = PoPeriod::factory()->create();
        $period->costs()->create(['name' => 'Lama', 'type' => 'per_batch', 'amount' => 1]);

        $this->put("/admin/periods/{$period->id}/stock", [
            'extra_sent' => 1,
            'extra' => [
                ['name' => 'Botol 250 ml', 'type' => 'per_bottle', 'size' => '250 ml', 'amount' => '2.500'],
                ['name' => 'Es batu', 'type' => 'per_batch', 'size' => '250 ml', 'amount' => '20.000'],
                ['name' => '', 'type' => 'per_bottle', 'size' => '', 'amount' => ''],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(
            [['Botol 250 ml', 'per_bottle', '250 ml', 2500], ['Es batu', 'per_batch', null, 20000]],
            $period->costs()->get()->map(fn ($c) => [$c->name, $c->type, $c->size, $c->amount])->all(),
        );
    }

    public function test_half_filled_cost_row_is_rejected(): void
    {
        $period = PoPeriod::factory()->create();

        $this->put("/admin/periods/{$period->id}/stock", [
            'extra_sent' => 1,
            'extra' => [['name' => '', 'type' => 'per_bottle', 'amount' => '2000']],
        ])->assertSessionHasErrors('extra.0.name');

        $this->assertSame(0, $period->costs()->count());
    }

    public function test_prefill_copies_other_costs_from_previous_period(): void
    {
        $previous = PoPeriod::factory()->create(['created_at' => now()->subWeek()]);
        $previous->costs()->create(['name' => 'Label stiker', 'type' => 'per_bottle', 'amount' => 500]);
        $period = PoPeriod::factory()->create();

        $this->get("/admin/periods/{$period->id}/stock?prefill=previous")
            ->assertSee('value="Label stiker"', false);
    }
}
