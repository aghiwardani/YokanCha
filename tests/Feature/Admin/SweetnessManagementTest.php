<?php

namespace Tests\Feature\Admin;

use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use App\Models\SweetnessLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StocksMenu;
use Tests\TestCase;

class SweetnessManagementTest extends TestCase
{
    use RefreshDatabase;
    use StocksMenu;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_levels_are_listed_least_sweet_first_on_the_master_page(): void
    {
        $this->sweetness('Extra manis', 150);
        $this->sweetness('Tanpa gula', 0);
        $this->sweetness('Less sugar', 50);

        $this->actingAs($this->admin())->get('/admin/master')
            ->assertOk()
            ->assertSee('id="manis"', false)
            ->assertSeeInOrder(['Tanpa gula', '0%', 'Less sugar', '50%', 'Extra manis', '150%']);
    }

    public function test_admin_can_add_change_and_delete_a_level(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/sweetness', ['_form' => 'sweetness-new', 'name' => 'Sedikit manis', 'percent' => '75'])
            ->assertRedirect('/admin/master#manis');
        $level = SweetnessLevel::where('name', 'Sedikit manis')->firstOrFail();
        $this->assertSame(75, $level->percent);

        $this->put("/admin/sweetness/{$level->id}", ['name' => 'Sedikit manis', 'percent' => '70'])
            ->assertRedirect('/admin/master#manis');
        $this->assertSame(70, $level->fresh()->percent);

        $this->delete("/admin/sweetness/{$level->id}")
            ->assertSessionHas('status', 'Tingkat manis berhasil dihapus.');
        $this->assertModelMissing($level);
    }

    public function test_percent_must_be_a_whole_number_between_0_and_300(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/sweetness', ['name' => 'Gila manis', 'percent' => '400'])
            ->assertSessionHasErrors('percent');

        $this->assertDatabaseCount('sweetness_levels', 0);
    }

    public function test_level_offered_by_a_product_keeps_its_name_and_cannot_be_deleted(): void
    {
        $level = $this->sweetness('Less sugar', 50);
        Product::factory()->create(['sweetness_options' => ['Less sugar']]);
        $this->actingAs($this->admin());

        $this->put("/admin/sweetness/{$level->id}", ['name' => 'Kurang manis', 'percent' => '50'])
            ->assertSessionHasErrors('name');
        $this->delete("/admin/sweetness/{$level->id}")->assertSessionHas('error');

        $this->assertSame('Less sugar', $level->fresh()->name);
    }

    public function test_menu_lists_levels_least_sweet_first_and_preselects_normal(): void
    {
        $this->sweetness('Less sugar', 50);
        $this->sweetness('Manis normal', 100);
        $this->sweetness('Extra manis', 150);
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'open']);
        $product = Product::create([
            'name' => 'Latte', 'base_price' => 30000, 'size_options' => ['250 ml'],
            'sweetness_options' => ['Extra manis', 'Manis normal', 'Less sugar'],
        ]);
        $milk = $this->ingredient('Susu');
        $gula = $this->ingredient('Gula', 'ml', true);
        $this->stock($period, $milk, 2000);
        $this->stock($period, $gula, 500);
        $this->recipe($product, '250 ml', $milk, 200);
        $this->recipe($product, '250 ml', $gula, 20);
        PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $product->id]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/value="Less sugar".*value="Manis normal".*value="Extra manis"/s', $html);
        $this->assertMatchesRegularExpression('/value="Manis normal"[^>]*checked/s', $html);
    }
}
