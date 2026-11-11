<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_redirected_from_admin_dashboard(): void
    {
        $response = $this->get('/admin/periods');

        $response->assertRedirect('/admin/login');
    }

    public function test_non_admin_user_is_forbidden_from_admin_pages(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/admin/periods')->assertForbidden();
        $this->get('/admin/orders')->assertForbidden();
    }

    public function test_non_admin_cannot_log_in_to_admin_panel(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_admin' => false,
        ]);

        $this->post('/admin/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_with_valid_credentials_grants_access(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@yokancha.test',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'admin@yokancha.test',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/admin/periods');
        $this->assertAuthenticated();

        $response = $this->get('/admin/periods');

        $response->assertOk();
    }
}
