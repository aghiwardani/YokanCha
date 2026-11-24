<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\RecipeExportController;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StocksMenu;
use Tests\TestCase;

class RecipeExportTest extends TestCase
{
    use RefreshDatabase;
    use StocksMenu;

    private function menu(): void
    {
        $this->sweetness('No Sugar', 0);
        $this->sweetness('Less Sugar', 50);
        $this->sweetness('Normal', 100);

        $maple = Product::create([
            'name' => 'Maple Signature', 'base_price' => 32000, 'sort_order' => 1,
            'size_options' => ['250 ml', '1 Liter'], 'size_prices' => ['250 ml' => 28000, '1 Liter' => 105000],
            'sweetness_options' => ['Normal', 'No Sugar', 'Less Sugar'],
        ]);
        $milk = $this->ingredient('Fresh Milk');
        $matcha = $this->ingredient('Matcha Tokyo', 'g');
        $syrup = $this->ingredient('Maple Syrup', 'ml', true);
        $this->recipe($maple, '250 ml', $milk, 200);
        $this->recipe($maple, '250 ml', $matcha, 4);
        $this->recipe($maple, '250 ml', $syrup, 20);
        $this->recipe($maple, '1 Liter', $milk, 800);
        $this->recipe($maple, '1 Liter', $matcha, 16);
        $this->recipe($maple, '1 Liter', $syrup, 80);

        Product::create(['name' => 'Menu Lama', 'base_price' => 20000, 'is_active' => false, 'size_options' => ['250 ml']]);
    }

    public function test_downloads_a_pdf_of_all_recipes(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->menu();

        $response = $this->get(route('admin.recipes.pdf'));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('resep-yokancha-', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_recipe_book_lists_amounts_per_size_and_sugar_per_level(): void
    {
        $this->menu();

        $html = view('admin.recipes.pdf', app(RecipeExportController::class)->book())->render();

        // Active menus first, inactive marked.
        $this->assertMatchesRegularExpression('/Maple Signature.*Menu Lama.*Nonaktif/s', $html);
        // Recipe grid per size.
        $this->assertStringContainsString('200 ml', $html);
        $this->assertStringContainsString('800 ml', $html);
        $this->assertStringContainsString('16 g', $html);
        $this->assertStringContainsString('Rp105.000', $html);
        // Sugar per sweetness level, least sweet first: none, half, full.
        $this->assertMatchesRegularExpression('/No Sugar.*tanpa.*Less Sugar.*10 ml.*40 ml.*Normal.*20 ml.*80 ml/s', $html);
        // The inactive menu has no recipe yet.
        $this->assertStringContainsString('Resep menu ini belum diisi.', $html);
    }

    public function test_master_page_has_the_export_button_and_guests_cannot_download(): void
    {
        $this->get(route('admin.recipes.pdf'))->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->admin()->create());
        $this->menu();

        $this->get(route('admin.master'))->assertSee(route('admin.recipes.pdf'), false)->assertSee('Export PDF resep');
    }
}
