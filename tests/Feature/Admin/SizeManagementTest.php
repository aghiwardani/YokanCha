<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SizeManagementTest extends TestCase
{
    use RefreshDatabase;

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
            ->get('/admin/sizes')
            ->assertOk()
            ->assertSeeInOrder(['250 ml', '500 ml', '1 liter']);
    }

    public function test_admin_can_create_size(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/sizes', ['_form' => 'new', 'name' => '500 ml', 'volume_ml' => 500])
            ->assertRedirect('/admin/sizes');

        $this->assertDatabaseHas('sizes', ['name' => '500 ml', 'volume_ml' => 500]);
    }

    public function test_size_name_must_be_unique_and_volume_positive(): void
    {
        Size::create(['name' => '500 ml', 'volume_ml' => 500]);

        $this->actingAs($this->admin())
            ->post('/admin/sizes', ['_form' => 'new', 'name' => '500 ml', 'volume_ml' => 0])
            ->assertSessionHasErrors(['name', 'volume_ml']);

        $this->assertSame(1, Size::count());
    }

    public function test_admin_can_change_volume_of_used_size(): void
    {
        $size = Size::create(['name' => '500 ml', 'volume_ml' => 500]);
        Product::factory()->create(['size_options' => ['500 ml']]);

        $this->actingAs($this->admin())
            ->put("/admin/sizes/{$size->id}", ['_form' => "row-{$size->id}", 'name' => '500 ml', 'volume_ml' => 450])
            ->assertRedirect('/admin/sizes');

        $this->assertSame(450, $size->fresh()->volume_ml);
    }

    public function test_cannot_rename_size_used_by_a_product(): void
    {
        $size = Size::create(['name' => '500 ml', 'volume_ml' => 500]);
        Product::factory()->create(['size_options' => ['500 ml']]);

        $this->actingAs($this->admin())
            ->put("/admin/sizes/{$size->id}", ['_form' => "row-{$size->id}", 'name' => '500ml', 'volume_ml' => 500])
            ->assertSessionHasErrors('name');

        $this->assertSame('500 ml', $size->fresh()->name);
    }

    public function test_can_rename_unused_size(): void
    {
        $size = Size::create(['name' => '500ml', 'volume_ml' => 500]);

        $this->actingAs($this->admin())
            ->put("/admin/sizes/{$size->id}", ['_form' => "row-{$size->id}", 'name' => '500 ml', 'volume_ml' => 500])
            ->assertRedirect('/admin/sizes');

        $this->assertSame('500 ml', $size->fresh()->name);
    }
}
