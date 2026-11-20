<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'name' => 'Admin', 'email' => 'admin@yokancha.local', 'password' => 'ChangeMe123!',
        ]);
    }

    public function test_settings_page_shows_current_shop_info(): void
    {
        $this->actingAs($this->admin());
        config(['yokancha.payment.bank' => 'BCA', 'yokancha.payment.account_number' => '111', 'yokancha.payment.account_name' => 'Lama']);

        $this->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('Info toko')
            ->assertSee('Akun admin')
            ->assertSee('BCA 111 a.n. Lama');
    }

    public function test_shop_info_is_saved_and_overrides_env(): void
    {
        $this->actingAs($this->admin());

        $this->put(route('admin.settings.shop'), [
            'whatsapp' => '0898 4527 337',
            'bank_name' => 'Mandiri',
            'bank_account' => '123 456 7890',
            'bank_holder' => 'Aghi Wardani',
            'max_per_variant' => 10,
        ])->assertRedirect(route('admin.settings'))->assertSessionHas('status', 'Info toko disimpan.');

        $this->assertSame('628984527337', Setting::find('whatsapp')->value);
        $this->assertSame('1234567890', Setting::find('bank_account')->value);

        // A fresh request picks the saved values up instead of .env.
        config(['yokancha.payment.bank' => 'BCA', 'yokancha.max_per_variant' => 20]);
        $this->get(route('admin.settings'))->assertSee('Mandiri 1234567890 a.n. Aghi Wardani');
        $this->assertSame('Mandiri', config('yokancha.payment.bank'));
        $this->assertSame(10, config('yokancha.max_per_variant'));
    }

    public function test_shop_info_is_validated(): void
    {
        $this->actingAs($this->admin());

        $this->put(route('admin.settings.shop'), [
            'whatsapp' => 'abc', 'bank_name' => '', 'bank_account' => 'x1', 'bank_holder' => '', 'max_per_variant' => 0,
        ])->assertSessionHasErrorsIn('shop', ['whatsapp', 'bank_name', 'bank_account', 'bank_holder', 'max_per_variant']);

        $this->assertSame(0, Setting::count());
    }

    public function test_admin_can_change_password_with_current_password(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->put(route('admin.settings.account'), [
            'name' => 'Aghi', 'email' => 'aghi@yokancha.local',
            'current_password' => 'ChangeMe123!',
            'password' => 'MatchaEnak2026', 'password_confirmation' => 'MatchaEnak2026',
        ])->assertRedirect(route('admin.settings'))->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertSame('Aghi', $admin->name);
        $this->assertSame('aghi@yokancha.local', $admin->email);
        $this->assertTrue(Hash::check('MatchaEnak2026', $admin->password));

        $this->post('/admin/logout');
        $this->post(route('admin.login.store'), ['email' => 'aghi@yokancha.local', 'password' => 'MatchaEnak2026'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($admin->fresh());
    }

    public function test_account_change_needs_the_right_current_password(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->put(route('admin.settings.account'), [
            'name' => 'Admin', 'email' => 'admin@yokancha.local',
            'current_password' => 'salah',
            'password' => 'MatchaEnak2026', 'password_confirmation' => 'MatchaEnak2026',
        ])->assertSessionHasErrorsIn('account', 'current_password');

        $this->assertTrue(Hash::check('ChangeMe123!', $admin->fresh()->password));
    }

    public function test_new_password_must_be_confirmed_and_long_enough(): void
    {
        $this->actingAs($this->admin());

        $this->put(route('admin.settings.account'), [
            'name' => 'Admin', 'email' => 'admin@yokancha.local', 'current_password' => 'ChangeMe123!',
            'password' => 'pendek', 'password_confirmation' => 'beda',
        ])->assertSessionHasErrorsIn('account', 'password');
    }

    public function test_guests_cannot_open_settings(): void
    {
        $this->get(route('admin.settings'))->assertRedirect(route('admin.login'));
        $this->put(route('admin.settings.shop'), [])->assertRedirect(route('admin.login'));
    }
}
