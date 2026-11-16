<?php

namespace Tests\Feature\Admin;

use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngredientManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_ingredient(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/ingredients')->assertOk();

        $this->post('/admin/ingredients', [
            'name' => 'Matcha',
            'unit' => 'g',
        ])->assertRedirect('/admin/ingredients');

        $this->assertDatabaseHas('ingredients', [
            'name' => 'Matcha',
            'unit' => 'g',
            'is_sugar' => false,
        ]);
    }

    public function test_admin_can_update_ingredient(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $ingredient = Ingredient::create(['name' => 'Susu', 'unit' => 'g', 'is_sugar' => false]);

        $this->put("/admin/ingredients/{$ingredient->id}", [
            'name' => 'Susu UHT',
            'unit' => 'ml',
        ])->assertRedirect('/admin/ingredients');

        $this->assertDatabaseHas('ingredients', [
            'id' => $ingredient->id,
            'name' => 'Susu UHT',
            'unit' => 'ml',
        ]);

        // Re-saving with its own name must not trip the unique rule.
        $this->put("/admin/ingredients/{$ingredient->id}", [
            'name' => 'Susu UHT',
            'unit' => 'ml',
        ])->assertSessionHasNoErrors();
    }

    public function test_unit_must_be_g_or_ml(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post('/admin/ingredients', [
            'name' => 'Es',
            'unit' => 'kg',
        ])->assertSessionHasErrors('unit');

        $this->assertDatabaseMissing('ingredients', ['name' => 'Es']);
    }

    public function test_only_one_sugar_ingredient(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $first = Ingredient::create(['name' => 'Gula', 'unit' => 'g', 'is_sugar' => true]);

        $this->post('/admin/ingredients', [
            'name' => 'Gula Aren',
            'unit' => 'g',
            'is_sugar' => '1',
        ])->assertRedirect('/admin/ingredients');

        $second = Ingredient::where('name', 'Gula Aren')->firstOrFail();
        $this->assertTrue($second->is_sugar);
        $this->assertFalse($first->fresh()->is_sugar);

        // Updating the first one back to sugar flips the second off.
        $this->put("/admin/ingredients/{$first->id}", [
            'name' => 'Gula',
            'unit' => 'g',
            'is_sugar' => '1',
        ]);

        $this->assertTrue($first->fresh()->is_sugar);
        $this->assertFalse($second->fresh()->is_sugar);
        $this->assertSame(1, Ingredient::where('is_sugar', true)->count());
    }

    public function test_guest_cannot_access_ingredients(): void
    {
        $this->get('/admin/ingredients')->assertRedirect('/admin/login');
        $this->post('/admin/ingredients', ['name' => 'X', 'unit' => 'g'])->assertRedirect('/admin/login');
    }
}
