<?php

namespace Tests\Feature\Admin;

use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemIngredient;
use App\Models\PoPeriod;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StocksMenu;
use Tests\TestCase;

class ProductionTest extends TestCase
{
    use RefreshDatabase;
    use StocksMenu;

    private function order(PoPeriod $period, string $name, string $status, array $lines): Order
    {
        $order = Order::create([
            'po_period_id' => $period->id,
            'customer_name' => $name,
            'customer_whatsapp' => '081234567890',
            'payment_status' => 'unpaid',
            'order_status' => $status,
            'order_number' => 'YC-'.uniqid(),
        ]);

        foreach ($lines as [$product, $size, $sweetness, $qty, $usage]) {
            $item = OrderItem::create([
                'order_id' => $order->id, 'product_id' => $product->id,
                'size' => $size, 'sweetness' => $sweetness, 'qty' => $qty, 'unit_price' => 32000,
            ]);
            foreach ($usage as [$ingredient, $perBottle]) {
                OrderItemIngredient::create([
                    'order_item_id' => $item->id, 'ingredient_id' => $ingredient->id, 'amount' => $perBottle * $qty,
                ]);
            }
        }

        return $order;
    }

    public function test_sheet_adds_up_bottles_and_ingredients_of_the_batch(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $period = PoPeriod::create(['name' => 'PO Jumat', 'status' => 'closed']);
        $product = Product::create(['name' => 'Maple Signature', 'base_price' => 32000, 'size_options' => ['250 ml']]);
        $milk = $this->ingredient('Fresh Milk');
        $matcha = $this->ingredient('Matcha Tokyo', 'g');
        $this->stock($period, $milk, 2000);

        $usage = [[$milk, 200], [$matcha, 4]];
        $this->order($period, 'Nui', 'new', [[$product, '250 ml', 'Less Sugar', 2, $usage]]);
        $this->order($period, 'Santi', 'diproses', [[$product, '250 ml', 'Less Sugar', 3, $usage]]);
        $this->order($period, 'Batal', 'cancelled', [[$product, '250 ml', 'Less Sugar', 10, $usage]]);

        $this->get(route('admin.periods.production', $period))
            ->assertOk()
            ->assertSee('Produksi PO Jumat')
            ->assertSee('5 botol dari 2 pesanan')
            ->assertSeeInOrder(['Maple Signature', '250 ml', 'Less Sugar', '5'])
            ->assertSee('1.000')   // fresh milk: 5 x 200 ml
            ->assertSee('Sisa')
            ->assertSee('Nui')
            ->assertSee('Santi')
            ->assertDontSee('Batal');
    }

    public function test_sheet_warns_while_po_is_still_open(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'open', 'closes_at' => now()->addHour()]);

        $this->get(route('admin.periods.production', $period))
            ->assertOk()
            ->assertSee('PO ini masih buka')
            ->assertSee('Belum ada pesanan di periode ini.');
    }

    public function test_orders_page_links_to_the_sheet_for_the_chosen_period(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'closed']);

        $this->get('/admin/orders?po_period_id='.$period->id)
            ->assertSee(route('admin.periods.production', $period), false);
    }

    public function test_guests_cannot_see_the_sheet(): void
    {
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'closed']);

        $this->get(route('admin.periods.production', $period))->assertRedirect();
    }

    public function test_sheet_works_out_hpp_and_profit(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'closed']);
        $product = Product::create(['name' => 'Matcha Oat', 'base_price' => 32000, 'size_options' => ['250 ml']]);
        $milk = $this->ingredient('Fresh Milk');
        $matcha = $this->ingredient('Matcha', 'g');
        // Rp20 per ml and Rp1.000 per g.
        $this->stock($period, $milk, 2000, 1000)->update(['cost' => 40000]);
        $this->stock($period, $matcha, 100, 20)->update(['cost' => 100000]);

        // 5 bottles x (200 ml milk + 4 g matcha) = Rp4.000 + Rp4.000 = Rp8.000 per bottle.
        $this->order($period, 'Nui', 'new', [[$product, '250 ml', '-', 5, [[$milk, 200], [$matcha, 4]]]]);

        $this->get(route('admin.periods.production', $period))
            ->assertOk()
            ->assertSee('HPP (COGS) Rp8.000')
            ->assertSee('Untung Rp24.000')
            ->assertSee('Rp160.000')   // penjualan 5 x 32.000
            ->assertSee('Rp40.000')    // HPP 5 x 8.000
            ->assertSee('Rp120.000')   // untung kotor
            ->assertSee('Rp140.000')   // belanja bahan
            ->assertSee('Rp20.000')    // untung bersih 160.000 - 140.000
            ->assertSee('Margin 75%')
            ->assertDontSee('Harga beli belum diisi');
    }

    public function test_sheet_lists_ingredients_without_a_price(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'closed']);
        $product = Product::create(['name' => 'Matcha Oat', 'base_price' => 32000, 'size_options' => ['250 ml']]);
        $milk = $this->ingredient('Oatside');
        $this->stock($period, $milk, 2000, 200);
        $this->order($period, 'Nui', 'new', [[$product, '250 ml', '-', 1, [[$milk, 200]]]]);

        $this->get(route('admin.periods.production', $period))
            ->assertSee('Harga beli belum diisi untuk: Oatside.');
    }

    public function test_cogs_includes_packaging_and_batch_costs(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'closed']);
        $product = Product::create(['name' => 'Matcha Oat', 'base_price' => 32000, 'size_options' => ['250 ml', '1 liter']]);
        $milk = $this->ingredient('Fresh Milk');
        $matcha = $this->ingredient('Matcha', 'g');
        $this->stock($period, $milk, 2000, 1000)->update(['cost' => 40000]);
        $this->stock($period, $matcha, 100, 20)->update(['cost' => 100000]);

        $period->costs()->create(['name' => 'Botol 250 ml', 'type' => 'per_bottle', 'size' => '250 ml', 'amount' => 2000]);
        $period->costs()->create(['name' => 'Botol 1 liter', 'type' => 'per_bottle', 'size' => '1 liter', 'amount' => 6000]);
        $period->costs()->create(['name' => 'Label', 'type' => 'per_bottle', 'size' => null, 'amount' => 500]);
        $period->costs()->create(['name' => 'Es batu', 'type' => 'per_batch', 'size' => null, 'amount' => 10000]);

        $this->order($period, 'Nui', 'new', [[$product, '250 ml', '-', 5, [[$milk, 200], [$matcha, 4]]]]);

        // Per bottle: bahan 8.000 + botol 2.000 + label 500 + es 10.000/5 = 12.500.
        $this->get(route('admin.periods.production', $period))
            ->assertOk()
            ->assertSee('HPP (COGS) Rp12.500')
            ->assertSee('Untung Rp19.500')
            ->assertSee('Rp62.500')    // COGS total
            ->assertSee('Rp97.500')    // untung kotor
            ->assertSee('Rp22.500')    // biaya lain: 5 x 2.500 + 10.000
            ->assertSee('-Rp2.500');   // 160.000 - 140.000 - 22.500
    }

    public function test_recipes_can_be_refreshed_from_the_master(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'closed']);
        $product = Product::create(['name' => 'Matcha Oat', 'base_price' => 32000, 'size_options' => ['250 ml']]);
        $milk = $this->ingredient('Oatside');
        $matcha = $this->ingredient('Matcha', 'g');
        $milkStock = $this->stock($period, $milk, 2000, 400);
        // Ordered when the recipe was 200 ml oat per bottle (2 bottles), no matcha yet.
        $order = $this->order($period, 'Nui', 'new', [[$product, '250 ml', '-', 2, [[$milk, 200]]]]);
        $cancelled = $this->order($period, 'Batal', 'cancelled', [[$product, '250 ml', '-', 3, [[$milk, 200]]]]);

        // Recipe changed in the master afterwards.
        $this->recipe($product, '250 ml', $milk, 180);
        $this->recipe($product, '250 ml', $matcha, 4);

        $this->post(route('admin.periods.production.recipes', $period))
            ->assertRedirect(route('admin.periods.production', $period))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'dihitung ulang') && str_contains($m, 'Stok kurang untuk: Matcha'));

        $this->assertEquals(360, $milkStock->fresh()->used);
        $this->assertEquals(8, \App\Models\PoPeriodIngredient::where('ingredient_id', $matcha->id)->value('used'));
        $this->assertEquals(
            [$milk->id => 360.0, $matcha->id => 8.0],
            $order->items()->first()->ingredientUsages->mapWithKeys(fn ($u) => [$u->ingredient_id => (float) $u->amount])->all(),
        );
        // Cancelled orders are left alone.
        $this->assertEquals(600, (float) $cancelled->items()->first()->ingredientUsages()->value('amount'));
    }

    public function test_prices_can_be_filled_from_the_master(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'closed']);
        $oat = $this->ingredient('Oatside');
        $oat->update(['pack_size' => 1000, 'pack_price' => 38000]);
        $matcha = $this->ingredient('Matcha', 'g');
        $oatStock = $this->stock($period, $oat, 2000);
        $matchaStock = $this->stock($period, $matcha, 100);
        $matchaStock->update(['cost' => 5000]);

        $this->post(route('admin.periods.production.prices', $period))
            ->assertSessionHas('status', 'Harga beli 1 bahan diisi dari harga master. Belum ada harga di master untuk: Matcha.');

        $this->assertSame(76000, $oatStock->fresh()->cost);
        $this->assertSame(5000, $matchaStock->fresh()->cost);
    }

    public function test_master_prices_only_reprice_the_new_purchase(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'closed']);
        $oat = $this->ingredient('Oatside');
        $oat->update(['pack_size' => 1000, 'pack_price' => 38000]);
        // 500 ml carried in worth Rp10.000, plus 1.000 ml bought.
        $row = $this->stock($period, $oat, 1500);
        $row->update(['carried' => 500, 'carried_cost' => 10000, 'cost' => 99999]);

        $this->post(route('admin.periods.production.prices', $period));

        $this->assertSame(48000, $row->fresh()->cost); // 10.000 + 1.000 x Rp38
    }

    public function test_ingredient_master_saves_pack_price(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.ingredients.store'), [
            'name' => 'Oatside', 'unit' => 'ml', 'pack_size' => '1.000', 'pack_price' => '38.000',
        ])->assertSessionHasNoErrors();

        $oat = \App\Models\Ingredient::where('name', 'Oatside')->first();
        $this->assertEquals(38.0, $oat->unitPrice());

        $this->post(route('admin.ingredients.store'), ['name' => 'Susu', 'unit' => 'ml', 'pack_price' => '20.000'])
            ->assertSessionHasErrors('pack_size');
    }
}
