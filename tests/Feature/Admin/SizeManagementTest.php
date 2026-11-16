<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StocksMenu;
use Tests\TestCase;

class SizeManagementTest extends TestCase
{
    use RefreshDatabase;
    use StocksMenu;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_guest_cannot_access_sizes(): void
    {
        $this->get('/admin/sizes')->assertRedirect('/admin/login');
    }

    public function test_sizes_are_listed_smallest_first(): void
    {
        Size::create(['name' => '1 liter', 'volume_ml' => 1000]);
        Size::create(['name' => '250 ml', 'volume_ml' => 250]);
        Size::create(['name' => '500 ml', 'volume_ml' => 500]);

        $this->actingAs($this->admin())
            ->get('/admin/master')
            ->assertOk()
            ->assertSeeInOrder(['250 ml', '500 ml', '1 liter']);
    }

    public function test_admin_can_create_size(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/sizes', ['_form' => 'size-new', 'name' => '500 ml', 'volume_ml' => 500])
            ->assertRedirect('/admin/master#ukuran');

        $this->assertDatabaseHas('sizes', ['name' => '500 ml', 'volume_ml' => 500]);
    }

    public function test_volume_typed_with_a_thousand_dot_is_read_correctly(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/sizes', ['_form' => 'size-new', 'name' => '1 liter', 'volume_ml' => '1.000'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sizes', ['name' => '1 liter', 'volume_ml' => 1000]);
    }

    public function test_size_name_must_be_unique_and_volume_positive(): void
    {
        Size::create(['name' => '500 ml', 'volume_ml' => 500]);

        $this->actingAs($this->admin())
            ->post('/admin/sizes', ['_form' => 'size-new', 'name' => '500 ml', 'volume_ml' => 0])
            ->assertSessionHasErrors(['name', 'volume_ml']);

        $this->assertSame(1, Size::count());
    }

    public function test_admin_can_change_volume_of_used_size(): void
    {
        $size = Size::create(['name' => '500 ml', 'volume_ml' => 500]);
        Product::factory()->create(['size_options' => ['500 ml']]);

        $this->actingAs($this->admin())
            ->put("/admin/sizes/{$size->id}", ['_form' => "size-row-{$size->id}", 'name' => '500 ml', 'volume_ml' => 450])
            ->assertRedirect('/admin/master#ukuran');

        $this->assertSame(450, $size->fresh()->volume_ml);
    }

    public function test_renaming_a_size_updates_products_prices_and_recipes(): void
    {
        $size = Size::create(['name' => '500ml', 'volume_ml' => 500]);
        $latte = Product::factory()->create([
            'size_options' => ['250 ml', '500ml'],
            'size_prices' => ['250 ml' => 30000, '500ml' => 55000],
        ]);
        $milk = $this->ingredient('Susu');
        $this->recipe($latte, '500ml', $milk, 400);
        $this->recipe($latte, '250 ml', $milk, 200);

        $this->actingAs($this->admin())
            ->put("/admin/sizes/{$size->id}", ['_form' => "size-row-{$size->id}", 'name' => '500 ml', 'volume_ml' => 500])
            ->assertRedirect('/admin/master#ukuran')
            ->assertSessionHas('status', 'Ukuran berhasil diubah. 1 produk ikut diperbarui.');

        $latte->refresh();
        $this->assertSame('500 ml', $size->fresh()->name);
        $this->assertSame(['250 ml', '500 ml'], $latte->size_options);
        $this->assertSame(55000, $latte->priceFor('500 ml'));
        $this->assertArrayNotHasKey('500ml', $latte->size_prices);
        $this->assertEquals(400, $latte->recipes()->where('size', '500 ml')->value('amount'));
        $this->assertSame(0, $latte->recipes()->where('size', '500ml')->count());
        $this->assertEquals(200, $latte->recipes()->where('size', '250 ml')->value('amount'));
    }

    public function test_past_orders_keep_the_size_name_they_were_placed_with(): void
    {
        $size = Size::create(['name' => '500ml', 'volume_ml' => 500]);
        $product = Product::factory()->create(['size_options' => ['500ml']]);
        $order = \App\Models\Order::create([
            'po_period_id' => \App\Models\PoPeriod::create(['name' => 'PO', 'status' => 'open'])->id,
            'customer_name' => 'Budi', 'customer_whatsapp' => '0812', 'order_number' => 'YC-1',
        ]);
        $item = \App\Models\OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'size' => '500ml', 'sweetness' => '-', 'qty' => 1, 'unit_price' => 1]);

        $this->actingAs($this->admin())
            ->put("/admin/sizes/{$size->id}", ['name' => '500 ml', 'volume_ml' => 500]);

        $this->assertSame('500ml', $item->fresh()->size);
    }

    public function test_can_rename_unused_size(): void
    {
        $size = Size::create(['name' => '500ml', 'volume_ml' => 500]);

        $this->actingAs($this->admin())
            ->put("/admin/sizes/{$size->id}", ['_form' => "size-row-{$size->id}", 'name' => '500 ml', 'volume_ml' => 500])
            ->assertRedirect('/admin/master#ukuran');

        $this->assertSame('500 ml', $size->fresh()->name);
    }
}
