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

        $this->get('/admin/master')->assertOk();

        $this->post('/admin/ingredients', [
            'name' => 'Matcha',
            'unit' => 'g',
        ])->assertRedirect('/admin/master#bahan');

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
        ])->assertRedirect('/admin/master#bahan');

        $this->assertDatabaseHas('ingredients', [
            'id' => $ingredient->id,
            'name' => 'Susu UHT',
            'unit' => 'ml',
        ]);

        // Re-saving with its own name must not trip the unique rule.
        $this->put("/admin/ingredients/{$ingredient->id}", [
            'name' => 'Susu UHT',
            'unit' => 'ml',
        ])->assertRedirect('/admin/master#bahan')->assertSessionHasNoErrors();
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

    public function test_several_ingredients_can_be_sugar(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $maple = Ingredient::create(['name' => 'Maple Syrup', 'unit' => 'ml', 'is_sugar' => true]);

        $this->post('/admin/ingredients', [
            'name' => 'Simple Syrup',
            'unit' => 'ml',
            'is_sugar' => '1',
        ])->assertRedirect('/admin/master#bahan');

        $this->assertTrue(Ingredient::where('name', 'Simple Syrup')->firstOrFail()->is_sugar);
        $this->assertTrue($maple->fresh()->is_sugar);
        $this->assertSame(2, Ingredient::where('is_sugar', true)->count());

        // Unticking one leaves the other alone.
        $this->put("/admin/ingredients/{$maple->id}", ['name' => 'Maple Syrup', 'unit' => 'ml']);

        $this->assertFalse($maple->fresh()->is_sugar);
        $this->assertSame(1, Ingredient::where('is_sugar', true)->count());
    }

    public function test_store_rejects_duplicate_name(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Ingredient::create(['name' => 'Susu', 'unit' => 'g', 'is_sugar' => false]);

        $this->post('/admin/ingredients', ['name' => 'Susu', 'unit' => 'g'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Ingredient::where('name', 'Susu')->count());
    }

    public function test_update_rejects_another_ingredients_name(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Ingredient::create(['name' => 'Susu', 'unit' => 'g', 'is_sugar' => false]);
        $other = Ingredient::create(['name' => 'Teh', 'unit' => 'g', 'is_sugar' => false]);

        $this->put("/admin/ingredients/{$other->id}", ['name' => 'Susu', 'unit' => 'g'])
            ->assertSessionHasErrors('name');

        $this->assertSame('Teh', $other->fresh()->name);
    }

    public function test_failed_update_does_not_prefill_add_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Ingredient::create(['name' => 'Susu', 'unit' => 'g', 'is_sugar' => false]);
        $other = Ingredient::create(['name' => 'Teh', 'unit' => 'g', 'is_sugar' => false]);

        $response = $this->from('/admin/master')->put("/admin/ingredients/{$other->id}", [
            '_form' => "ingredient-row-{$other->id}",
            'name' => 'Susu',
            'unit' => 'ml',
            'is_sugar' => '1',
        ]);
        $response->assertRedirect('/admin/master')->assertSessionHasErrors('name');

        $page = $this->get('/admin/master');
        $html = $page->getContent();

        // The add form stays empty: its name input has an empty value and no checked box.
        preg_match('/<form[^>]*ingredients"[^>]*>.*?<\/form>/s', $html, $add);
        $this->assertStringNotContainsString('value="Susu"', $add[0]);
        $this->assertStringNotContainsString('checked', $add[0]);
        $this->assertStringNotContainsString('selected', preg_replace('/<option value="g" selected>/', '', $add[0]) ?? '');

        // The failed row keeps the submitted value and shows the error.
        $this->assertStringContainsString('value="Susu"', $html);
        $page->assertSee('The name has already been taken.');
    }

    public function test_guest_cannot_access_ingredients(): void
    {
        $this->get('/admin/ingredients')->assertRedirect('/admin/login');
        $this->post('/admin/ingredients', ['name' => 'X', 'unit' => 'g'])->assertRedirect('/admin/login');
    }
}
