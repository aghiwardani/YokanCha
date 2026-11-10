<?php

namespace Tests\Feature\Admin;

use App\Models\PoPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_period(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->post('/admin/periods', [
            'name' => 'PO November 2026',
            'opens_at' => '2026-11-15 00:00:00',
            'closes_at' => '2026-11-20 23:59:59',
        ]);

        $response->assertRedirect('/admin/periods');

        $this->assertDatabaseHas('po_periods', [
            'name' => 'PO November 2026',
            'status' => 'draft',
        ]);
    }

    public function test_admin_can_open_and_close_period(): void
    {
        $this->actingAs(User::factory()->create());

        $period = PoPeriod::factory()->create([
            'status' => 'draft',
        ]);

        $response = $this->post("/admin/periods/{$period->id}/open");
        $response->assertRedirect('/admin/periods');
        $this->assertSame('open', $period->fresh()->status);

        $response = $this->post("/admin/periods/{$period->id}/close");
        $response->assertRedirect('/admin/periods');
        $this->assertSame('closed', $period->fresh()->status);
    }
}
