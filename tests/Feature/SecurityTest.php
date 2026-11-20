<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_send_security_headers(): void
    {
        $this->get('/')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy')
            // No HSTS over plain HTTP.
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->get('/admin/login')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_admin_login_locks_after_five_wrong_passwords(): void
    {
        User::factory()->admin()->create(['email' => 'admin@yokancha.local', 'password' => 'Benar12345']);

        foreach (range(1, 5) as $try) {
            $this->post('/admin/login', ['email' => 'admin@yokancha.local', 'password' => 'salah'.$try])
                ->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
        }

        // Even the right password is refused until the minute is over.
        $this->post('/admin/login', ['email' => 'admin@yokancha.local', 'password' => 'Benar12345'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Terlalu banyak percobaan login', session('errors')->first('email'));
        $this->assertGuest();

        $this->travel(61)->seconds();
        $this->post('/admin/login', ['email' => 'admin@yokancha.local', 'password' => 'Benar12345']);
        $this->assertAuthenticated();
    }

    public function test_successful_login_resets_the_counter(): void
    {
        User::factory()->admin()->create(['email' => 'admin@yokancha.local', 'password' => 'Benar12345']);

        foreach (range(1, 4) as $try) {
            $this->post('/admin/login', ['email' => 'admin@yokancha.local', 'password' => 'salah']);
        }
        $this->post('/admin/login', ['email' => 'admin@yokancha.local', 'password' => 'Benar12345']);
        $this->assertAuthenticated();
        $this->post('/admin/logout');

        $this->post('/admin/login', ['email' => 'admin@yokancha.local', 'password' => 'salah'])
            ->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
    }

    // One browser: the same session cookie on every request.
    private function asOneVisitor(): static
    {
        return $this->withCookie(config('session.cookie'), Str::random(40));
    }

    public function test_order_lookup_is_limited_to_ten_a_minute(): void
    {
        $this->asOneVisitor();

        foreach (range(1, 10) as $try) {
            $this->post('/orders/lookup', ['order_number' => 'YC-1-AAAAA'.$try, 'customer_whatsapp' => '081234567890'])->assertOk();
        }

        $this->post('/orders/lookup', ['order_number' => 'YC-1-ZZZZZZ', 'customer_whatsapp' => '081234567890'])
            ->assertStatus(429)
            ->assertSee('Pelan-pelan dulu.')
            ->assertSee('Kembali ke menu');
    }

    public function test_checkout_is_limited_to_ten_a_minute(): void
    {
        $this->asOneVisitor();

        foreach (range(1, 10) as $try) {
            $this->post('/checkout', [])->assertStatus(302);
        }

        $this->post('/checkout', [])->assertStatus(429);
    }

    public function test_colleagues_behind_one_office_ip_are_not_blocked_by_each_other(): void
    {
        // 30 different visitors from the same IP, one lookup each: all fine.
        foreach (range(1, 30) as $visitor) {
            $this->withCookie(config('session.cookie'), Str::random(40))
                ->post('/orders/lookup', ['order_number' => 'YC-1-AAAAAA', 'customer_whatsapp' => '081234567890'])
                ->assertOk();
        }
    }

    public function test_one_ip_is_still_capped_without_cookies(): void
    {
        // A script that drops cookies gets a new session each time; the IP cap stops it.
        foreach (range(1, 60) as $try) {
            $this->post('/orders/lookup', ['order_number' => 'YC-1-AAAAAA', 'customer_whatsapp' => '081234567890']);
        }

        $this->post('/orders/lookup', ['order_number' => 'YC-1-AAAAAA', 'customer_whatsapp' => '081234567890'])
            ->assertStatus(429);
    }
}
