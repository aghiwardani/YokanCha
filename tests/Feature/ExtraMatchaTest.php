<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Order;
use App\Models\PoPeriod;
use App\Models\PoPeriodIngredient;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use App\Models\Size;
use App\Models\User;
use App\Services\PeriodReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StocksMenu;
use Tests\TestCase;

class ExtraMatchaTest extends TestCase
{
    use RefreshDatabase;
    use StocksMenu;

    private PoPeriod $period;

    private PoPeriodProduct $ppp;

    private PoPeriodIngredient $matchaStock;

    protected function setUp(): void
    {
        parent::setUp();

        // 250 ml: +Rp5.000 for 50% more matcha; 1 liter: +Rp15.000 for 50%; 500 ml: no extra.
        Size::create(['name' => '250 ml', 'volume_ml' => 250, 'extra_matcha_price' => 5000, 'extra_matcha_percent' => 50]);
        Size::create(['name' => '500 ml', 'volume_ml' => 500]);
        Size::create(['name' => '1 liter', 'volume_ml' => 1000, 'extra_matcha_price' => 15000, 'extra_matcha_percent' => 50]);

        $this->period = PoPeriod::create(['name' => 'PO Jumat', 'status' => 'open']);
        $product = Product::create([
            'name' => 'Ceremonial Latte', 'base_price' => 28000,
            'size_options' => ['250 ml', '500 ml', '1 liter'],
            'size_prices' => ['250 ml' => 28000, '500 ml' => 52000, '1 liter' => 100000],
            'sweetness_options' => ['Normal'],
        ]);

        $matcha = Ingredient::create(['name' => 'Matcha Ceremonial', 'unit' => 'g', 'is_matcha' => true]);
        $milk = $this->ingredient('Fresh Milk');
        foreach (['250 ml' => [3, 200], '500 ml' => [6, 400], '1 liter' => [12, 800]] as $size => [$g, $ml]) {
            $this->recipe($product, $size, $matcha, $g);
            $this->recipe($product, $size, $milk, $ml);
        }
        $this->matchaStock = $this->stock($this->period, $matcha, 12);
        $this->stock($this->period, $milk, 5000);

        $this->ppp = PoPeriodProduct::create(['po_period_id' => $this->period->id, 'product_id' => $product->id]);
    }

    private function add(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('cart.add'), $data + [
            'po_period_product_id' => $this->ppp->id, 'qty' => 1, 'size' => '250 ml', 'sweetness' => 'Normal',
        ]);
    }

    public function test_menu_offers_extra_matcha_with_the_price_of_each_size(): void
    {
        $this->get(route('menu.index'))
            ->assertOk()
            ->assertSee('Extra matcha')
            ->assertSee('+Rp5.000')
            ->assertSee('data-extra="15000"', false)
            ->assertSee('data-extra="0"', false);
    }

    public function test_extra_matcha_is_its_own_cart_line_with_the_extra_price(): void
    {
        $this->add(['qty' => 1])->assertOk();
        $this->add(['qty' => 1, 'extra_matcha' => '1'])
            ->assertOk()
            ->assertJsonPath('message', 'Ceremonial Latte extra matcha masuk keranjang.');

        $this->get(route('cart.show'))
            ->assertSee('250 ml · Normal · Rp28.000/botol')
            ->assertSee('250 ml · Normal · Extra matcha · Rp33.000/botol');
    }

    public function test_size_without_extra_is_refused(): void
    {
        $this->add(['size' => '500 ml', 'extra_matcha' => '1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['extra_matcha' => 'Extra matcha belum ada untuk Ceremonial Latte ukuran 500 ml.']);
    }

    public function test_product_can_turn_extra_matcha_off(): void
    {
        $this->ppp->product->update(['allow_extra_matcha' => false]);

        $this->get(route('menu.index'))->assertOk()->assertDontSee('data-extra-toggle', false);
        $this->add(['extra_matcha' => '1'])->assertStatus(422)->assertJsonValidationErrors('extra_matcha');
    }

    public function test_product_form_saves_the_extra_matcha_switch(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $product = $this->ppp->product;

        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('Pembeli bisa pilih Extra matcha');
        $this->assertTrue($product->fresh()->allow_extra_matcha);
        $product->update(['allow_extra_matcha' => false]);
        $this->assertSame([], $product->fresh()->extraMatchaPrices());
    }

    public function test_extra_matcha_is_not_sold_until_an_ingredient_is_marked_matcha(): void
    {
        Ingredient::query()->update(['is_matcha' => false]);

        $this->get(route('menu.index'))->assertOk()->assertDontSee('data-extra-toggle', false);
        $this->add(['extra_matcha' => '1'])->assertStatus(422)->assertJsonValidationErrors('extra_matcha');

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.master'))->assertSee('Extra matcha belum tampil di menu');
    }

    public function test_extra_matcha_uses_more_matcha_stock(): void
    {
        // 12 g of matcha: 4 plain bottles (3 g) but only 2 with extra (+50% = 4,5 g).
        $this->add(['qty' => 3, 'extra_matcha' => '1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['qty' => 'Stok Ceremonial Latte extra matcha ukuran 250 ml tinggal cukup buat 2 botol lagi.']);

        $response = $this->add(['qty' => 2, 'extra_matcha' => '1'])->assertOk();
        // 9 g taken, 3 g left: one more plain bottle, no more extra.
        $this->assertSame(1, $response->json("availability.{$this->ppp->id}.cells.250 ml.Normal"));
        $this->assertSame(0, $response->json("availability.{$this->ppp->id}.extraCells.250 ml.Normal"));
    }

    public function test_extra_matcha_adds_water_and_takes_it_off_the_milk(): void
    {
        // 250 ml recipe: 3 g matcha, 40 ml water, 200 ml milk; extra is +50%.
        $product = $this->ppp->product;
        $milk = Ingredient::where('name', 'Fresh Milk')->first();
        $milk->update(['is_milk' => true]);
        $water = Ingredient::create(['name' => 'Air Mineral', 'unit' => 'ml', 'is_water' => true]);
        $this->recipe($product, '250 ml', $water, 40);
        $matchaId = $this->matchaStock->ingredient_id;

        $stock = app(\App\Services\StockService::class);
        $this->assertEquals([$matchaId => 3, $milk->id => 200, $water->id => 40], $stock->perBottle($product, '250 ml', 'Normal'));

        $extra = $stock->perBottle($product, '250 ml', 'Normal', true);
        $this->assertEquals(4.5, $extra[$matchaId]);   // +50% matcha
        $this->assertEquals(60, $extra[$water->id]);   // +50% water (+20 ml)
        $this->assertEquals(180, $extra[$milk->id]);   // milk down by the 20 ml of water
        $this->assertEquals(240, $extra[$water->id] + $extra[$milk->id]); // bottle holds the same

        // Two milks share the cut by their share: 150 + 50 ml -> 135 + 45 ml.
        $oat = Ingredient::create(['name' => 'Oat Milk', 'unit' => 'ml', 'is_milk' => true]);
        \App\Models\ProductRecipe::where('product_id', $product->id)->where('size', '250 ml')->where('ingredient_id', $milk->id)->update(['amount' => 150]);
        $this->recipe($product, '250 ml', $oat, 50);
        $extra = app(\App\Services\StockService::class)->perBottle($product, '250 ml', 'Normal', true);
        $this->assertEquals(135, $extra[$milk->id]);
        $this->assertEquals(45, $extra[$oat->id]);
    }

    public function test_milk_stays_when_the_recipe_has_no_water(): void
    {
        Ingredient::where('name', 'Fresh Milk')->update(['is_milk' => true]);
        $milkId = Ingredient::where('name', 'Fresh Milk')->value('id');

        $extra = app(\App\Services\StockService::class)->perBottle($this->ppp->product, '250 ml', 'Normal', true);
        $this->assertEquals(200, $extra[$milkId]);
    }

    public function test_master_saves_water_and_milk_flags(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.ingredients.store'), ['name' => 'Air', 'unit' => 'ml', 'is_water' => '1'])->assertSessionHasNoErrors();
        $this->post(route('admin.ingredients.store'), ['name' => 'Susu UHT', 'unit' => 'ml', 'is_milk' => '1'])->assertSessionHasNoErrors();

        $this->assertTrue(Ingredient::where('name', 'Air')->first()->is_water);
        $this->assertTrue(Ingredient::where('name', 'Susu UHT')->first()->is_milk);
        $this->get(route('admin.master'))->assertSee('Ini air')->assertSee('Ini susu');
    }

    public function test_checkout_saves_the_extra_matcha_price_and_usage(): void
    {
        $this->add(['qty' => 2, 'extra_matcha' => '1']);

        $this->post(route('checkout.store'), ['customer_name' => 'Rina', 'customer_whatsapp' => '081234567890'])
            ->assertRedirect();

        $item = Order::firstOrFail()->items()->firstOrFail();
        $this->assertTrue($item->extra_matcha);
        $this->assertSame(33000, $item->unit_price);
        $this->assertSame('250 ml, Normal, Extra matcha', $item->variantLabel());
        $this->assertEquals(9, $item->ingredientUsages()->where('ingredient_id', $this->matchaStock->ingredient_id)->value('amount'));
        $this->assertEquals(9, $this->matchaStock->fresh()->used);
        // Milk isn't matcha, so it stays at the recipe amount.
        $this->assertEquals(400, $item->ingredientUsages()->where('ingredient_id', '!=', $this->matchaStock->ingredient_id)->value('amount'));

        $this->get(route('checkout.confirmation', $item->order->order_number))
            ->assertSee('Extra matcha')
            ->assertSee(rawurlencode('(250 ml, Normal, Extra matcha)'), false);
    }

    public function test_production_and_report_keep_extra_matcha_apart(): void
    {
        $this->add(['qty' => 1]);
        $this->add(['qty' => 1, 'extra_matcha' => '1']);
        $this->post(route('checkout.store'), ['customer_name' => 'Rina', 'customer_whatsapp' => '081234567890']);

        $report = app(PeriodReport::class)->build($this->period);
        $this->assertCount(2, $report['variants']);
        $this->assertSame([false, true], array_column($report['variants'], 'extra'));
        $this->assertSame(['Biasa', 'Extra matcha'], collect($report['byExtra'])->sortBy('label')->pluck('label')->values()->all());

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.periods.production', $this->period))->assertOk()->assertSee('Extra matcha');
        $this->get(route('admin.reports.show', $this->period))->assertOk()->assertSee('Extra matcha');
    }

    public function test_manual_order_can_add_extra_matcha(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.orders.create', ['period' => $this->period->id]))
            ->assertOk()
            ->assertSee('data-extra-wrap', false);

        $this->post(route('admin.orders.store'), [
            'po_period_id' => $this->period->id,
            'customer_name' => 'Pak Budi', 'customer_whatsapp' => '081234567890',
            'payment_status' => 'unpaid', 'order_status' => 'new',
            'lines' => [['ppp' => $this->ppp->id, 'size' => '1 liter', 'sweetness' => 'Normal', 'qty' => '1', 'price' => '', 'extra' => '1']],
            'allow_short' => '1',
        ])->assertSessionHasNoErrors();

        $item = Order::firstOrFail()->items()->firstOrFail();
        $this->assertTrue($item->extra_matcha);
        $this->assertSame(115000, $item->unit_price);
        $this->assertEquals(18, $this->matchaStock->fresh()->used);

        $this->post(route('admin.orders.store'), [
            'po_period_id' => $this->period->id,
            'customer_name' => 'Pak Budi', 'customer_whatsapp' => '081234567890',
            'payment_status' => 'unpaid', 'order_status' => 'new',
            'lines' => [['ppp' => $this->ppp->id, 'size' => '500 ml', 'sweetness' => 'Normal', 'qty' => '1', 'extra' => '1']],
        ])->assertSessionHasErrors('lines.0.extra');
    }

    public function test_master_saves_extra_matcha_per_size_and_matcha_ingredient(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $size = Size::where('name', '500 ml')->first();

        $this->put(route('admin.sizes.update', $size), [
            'name' => '500 ml', 'volume_ml' => '500', 'extra_matcha_price' => '10.000', 'extra_matcha_percent' => '40',
        ])->assertSessionHasNoErrors();
        $this->assertSame(10000, $size->fresh()->extra_matcha_price);
        $this->assertSame(40, $size->fresh()->extra_matcha_percent);

        $this->put(route('admin.sizes.update', $size), [
            'name' => '500 ml', 'volume_ml' => '500', 'extra_matcha_price' => '', 'extra_matcha_percent' => '30',
        ])->assertSessionHasErrors('extra_matcha_price');

        $this->put(route('admin.sizes.update', $size), [
            'name' => '500 ml', 'volume_ml' => '500', 'extra_matcha_price' => '8000', 'extra_matcha_percent' => '',
        ])->assertSessionHasErrors('extra_matcha_percent');

        $this->put(route('admin.sizes.update', $size), [
            'name' => '500 ml', 'volume_ml' => '500', 'extra_matcha_price' => '', 'extra_matcha_percent' => '',
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $size->fresh()->extra_matcha_price);

        $this->post(route('admin.ingredients.store'), ['name' => 'Matcha Uji', 'unit' => 'g', 'is_matcha' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Ingredient::where('name', 'Matcha Uji')->first()->is_matcha);

        $this->get(route('admin.master'))->assertOk()->assertSee('Harga extra matcha')->assertSee('+Rp5.000');
    }
}
