<?php

namespace Tests\Feature\Admin;

use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_product(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->post('/admin/products', [
            'name' => 'Es Teh Lemon',
            'description' => 'Teh dingin dengan lemon segar',
            'base_price' => 15000,
            'size_options' => '250ml, 1L',
            'sweetness_options' => 'Normal, Kurang Gula',
        ]);

        $response->assertRedirect('/admin/products');

        $this->assertDatabaseHas('products', [
            'name' => 'Es Teh Lemon',
            'description' => 'Teh dingin dengan lemon segar',
            'base_price' => 15000,
        ]);

        $product = Product::where('name', 'Es Teh Lemon')->first();
        $this->assertSame(['250ml', '1L'], $product->size_options);
        $this->assertSame(['Normal', 'Kurang Gula'], $product->sweetness_options);
    }

    public function test_admin_can_set_price_per_size_and_display_fields(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post('/admin/products', [
            'name' => 'Maple Matcha Latte',
            'base_price' => 32000,
            'size_options' => '250 ml=32000, 1 liter=110000',
            'sweetness_options' => 'Manis normal, Less sugar',
            'tag' => 'Maple Signature',
            'tone' => 'amber',
            'bottle_style' => 'maple',
            'sort_order' => 1,
        ])->assertRedirect('/admin/products');

        $product = Product::where('name', 'Maple Matcha Latte')->first();
        $this->assertSame(['250 ml', '1 liter'], $product->size_options);
        $this->assertSame(['250 ml' => 32000, '1 liter' => 110000], $product->size_prices);
        $this->assertSame(110000, $product->priceFor('1 liter'));
        $this->assertSame('Maple Signature', $product->tag);
        $this->assertSame('amber', $product->tone);
        $this->assertSame('maple', $product->bottle_style);
        $this->assertSame(1, $product->sort_order);
    }

    public function test_admin_can_set_quota_for_period(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $product = Product::factory()->create();
        $period = PoPeriod::factory()->create();

        $response = $this->post("/admin/products/{$product->id}/quota", [
            'po_period_id' => $period->id,
            'quota' => 50,
        ]);

        $response->assertRedirect('/admin/products');

        $this->assertDatabaseHas('po_period_products', [
            'po_period_id' => $period->id,
            'product_id' => $product->id,
            'quota' => 50,
        ]);
    }

    public function test_admin_can_set_quota_upserts_without_touching_ordered_qty(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $product = Product::factory()->create();
        $period = PoPeriod::factory()->create();

        PoPeriodProduct::create([
            'po_period_id' => $period->id,
            'product_id' => $product->id,
            'quota' => 20,
            'ordered_qty' => 5,
        ]);

        $response = $this->post("/admin/products/{$product->id}/quota", [
            'po_period_id' => $period->id,
            'quota' => 80,
        ]);

        $response->assertRedirect('/admin/products');

        $this->assertDatabaseHas('po_period_products', [
            'po_period_id' => $period->id,
            'product_id' => $product->id,
            'quota' => 80,
            'ordered_qty' => 5,
        ]);

        $this->assertSame(1, PoPeriodProduct::where('product_id', $product->id)
            ->where('po_period_id', $period->id)
            ->count());
    }

    public function test_admin_can_edit_product(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $product = Product::factory()->create([
            'name' => 'Original Name',
        ]);

        $response = $this->put("/admin/products/{$product->id}", [
            'name' => 'Updated Name',
            'description' => $product->description,
            'base_price' => $product->base_price,
            'size_options' => implode(', ', $product->size_options ?? []),
            'sweetness_options' => implode(', ', $product->sweetness_options ?? []),
        ]);

        $response->assertRedirect('/admin/products');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
        ]);
    }
}
