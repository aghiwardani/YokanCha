<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_resets_the_only_admin_password(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'aghi@yokancha.com', 'password' => 'LupaPassword1']);

        $this->artisan('admin:reset-password')
            ->expectsOutputToContain('aghi@yokancha.com')
            ->expectsQuestion('Password baru (minimal 8 karakter, tidak terlihat saat diketik)', 'MatchaBaru2026')
            ->expectsQuestion('Ulangi password baru', 'MatchaBaru2026')
            ->expectsOutputToContain('Password baru tersimpan.')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('MatchaBaru2026', $admin->fresh()->password));
    }

    public function test_asks_again_when_passwords_differ_or_are_short(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'LupaPassword1']);

        $this->artisan('admin:reset-password')
            ->expectsQuestion('Password baru (minimal 8 karakter, tidak terlihat saat diketik)', 'pendek')
            ->expectsQuestion('Ulangi password baru', 'pendek')
            ->expectsOutput('Password minimal 8 karakter.')
            ->expectsQuestion('Password baru (minimal 8 karakter, tidak terlihat saat diketik)', 'MatchaBaru2026')
            ->expectsQuestion('Ulangi password baru', 'MatchaLain2026')
            ->expectsOutput('Kedua password tidak sama.')
            ->expectsQuestion('Password baru (minimal 8 karakter, tidak terlihat saat diketik)', 'MatchaBaru2026')
            ->expectsQuestion('Ulangi password baru', 'MatchaBaru2026')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('MatchaBaru2026', $admin->fresh()->password));
    }

    public function test_gives_up_after_three_bad_tries_without_changing_anything(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'LupaPassword1']);

        $command = $this->artisan('admin:reset-password');
        foreach (range(1, 3) as $try) {
            $command->expectsQuestion('Password baru (minimal 8 karakter, tidak terlihat saat diketik)', 'x')
                ->expectsQuestion('Ulangi password baru', 'x');
        }
        $command->expectsOutputToContain('Password tidak diubah.')->assertFailed();

        $this->assertTrue(Hash::check('LupaPassword1', $admin->fresh()->password));
    }

    public function test_picks_the_admin_by_email_and_never_touches_non_admins(): void
    {
        User::factory()->admin()->create(['email' => 'satu@yokancha.com']);
        $second = User::factory()->admin()->create(['email' => 'dua@yokancha.com', 'password' => 'LupaPassword1']);
        User::factory()->create(['email' => 'pembeli@example.com']);

        $this->artisan('admin:reset-password', ['--email' => 'pembeli@example.com'])
            ->expectsOutput('Tidak ada akun admin dengan email pembeli@example.com.')
            ->assertFailed();

        $this->artisan('admin:reset-password', ['--email' => 'dua@yokancha.com'])
            ->expectsQuestion('Password baru (minimal 8 karakter, tidak terlihat saat diketik)', 'MatchaBaru2026')
            ->expectsQuestion('Ulangi password baru', 'MatchaBaru2026')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('MatchaBaru2026', $second->fresh()->password));
    }

    public function test_asks_which_admin_when_there_are_several(): void
    {
        User::factory()->admin()->create(['email' => 'satu@yokancha.com']);
        $second = User::factory()->admin()->create(['email' => 'dua@yokancha.com']);

        $this->artisan('admin:reset-password')
            ->expectsChoice('Akun admin yang mana?', 'dua@yokancha.com', ['satu@yokancha.com', 'dua@yokancha.com'])
            ->expectsQuestion('Password baru (minimal 8 karakter, tidak terlihat saat diketik)', 'MatchaBaru2026')
            ->expectsQuestion('Ulangi password baru', 'MatchaBaru2026')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('MatchaBaru2026', $second->fresh()->password));
    }

    public function test_says_so_when_there_is_no_admin(): void
    {
        $this->artisan('admin:reset-password')
            ->expectsOutputToContain('Belum ada akun admin.')
            ->assertFailed();
    }
}
