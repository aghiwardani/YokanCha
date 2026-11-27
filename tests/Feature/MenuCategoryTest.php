<?php

namespace Tests\Feature;

use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StocksMenu;
use Tests\TestCase;

class MenuCategoryTest extends TestCase
{
    use RefreshDatabase;
    use StocksMenu;

    private function product(string $name, string $category): Product
    {
        return Product::create(['name' => $name, 'category' => $category, 'base_price' => 25000]);
    }

    public function test_unknown_categories_become_matcha(): void
    {
        $this->assertSame('matcha', $this->product('Lama', 'minuman')->fresh()->category);
        $this->assertSame('coffee', $this->product('Kopi Susu', 'coffee')->fresh()->category);
    }

    public function test_menu_shows_matcha_and_coffee_tabs_with_matcha_open(): void
    {
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'open']);
        foreach ([$this->product('Ceremonial Latte', 'matcha'), $this->product('Kopi Susu Aren', 'coffee')] as $product) {
            PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $product->id]);
        }

        $html = $this->get(route('menu.index'))->assertOk()
            ->assertSee('data-menu-tabs', false)
            ->assertSeeInOrder(['data-menu-tab="matcha"', 'data-menu-tab="coffee"'], false)
            ->assertSee('Kopi Susu Aren')
            ->getContent();

        // Matcha panel is open, coffee panel is hidden until its tab is picked.
        $this->assertMatchesRegularExpression('/class=""\s+data-menu-panel="matcha"/', $html);
        $this->assertMatchesRegularExpression('/class="hidden"\s+data-menu-panel="coffee"/', $html);

        $coffee = $this->get(route('menu.index', ['kategori' => 'coffee']))->getContent();
        $this->assertMatchesRegularExpression('/class="hidden"\s+data-menu-panel="matcha"/', $coffee);
        $this->assertStringContainsString('id="menu-tab-coffee" role="tab" aria-controls="menu-panel-coffee" aria-selected="true"', preg_replace('/\s+/', ' ', $coffee));
    }

    public function test_no_coffee_tab_when_the_batch_has_no_coffee(): void
    {
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'open']);
        PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $this->product('Ceremonial Latte', 'matcha')->id]);
        // Coffee exists in master data but isn't sold in this batch.
        $this->product('Kopi Susu Aren', 'coffee');

        $this->get(route('menu.index'))->assertOk()
            ->assertDontSee('data-menu-tabs', false)
            ->assertDontSee('data-menu-panel="coffee"', false)
            ->assertSee('Ceremonial Latte');

        // ?kategori=coffee falls back to matcha (no coffee theme either).
        $this->get(route('menu.index', ['kategori' => 'coffee']))->assertOk()
            ->assertSee('data-theme="matcha"', false);

        // With no batch open, the catalog has both, so both tabs show.
        $period->update(['status' => 'closed']);
        $this->get(route('menu.index'))->assertOk()->assertSee('data-menu-tab="coffee"', false);
    }

    public function test_coffee_only_batch_opens_in_coffee(): void
    {
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'open']);
        PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $this->product('Kopi Susu Aren', 'coffee')->id]);

        $this->get(route('menu.index'))->assertOk()
            ->assertDontSee('data-menu-tabs', false)
            ->assertSee('data-theme="coffee"', false)
            ->assertSee('Kopi Susu Aren');
    }

    public function test_catalog_has_tabs_when_no_batch_is_open(): void
    {
        $this->product('Ceremonial Latte', 'matcha');
        $this->product('Kopi Susu Aren', 'coffee');

        $this->get(route('menu.index'))->assertOk()
            ->assertSee('data-menu-tabs', false)
            ->assertSee('data-menu-panel="coffee"', false);
    }

    public function test_admin_picks_the_category_in_the_product_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $product = $this->product('Kopi Susu Aren', 'matcha');

        $this->get(route('admin.products.edit', $product))->assertOk()
            ->assertSee('name="category" value="coffee"', false);
        $this->get(route('admin.master'))->assertOk()->assertSee('Matcha');
    }
}
