<?php

namespace Tests\Feature;

use Database\Seeders\MenuDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_returns_ok(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_menu_demo_seeder_makes_menu_orderable(): void
    {
        $this->seed(MenuDemoSeeder::class);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Matcha Latte Klasik');
        // A sold-out card renders a "Habis" tag span and a "Habis" button.
        $response->assertDontSee('>Habis</', false);
    }
}
