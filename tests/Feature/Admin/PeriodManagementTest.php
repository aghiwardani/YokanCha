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
        $this->actingAs(User::factory()->admin()->create());

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
        $this->actingAs(User::factory()->admin()->create());

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

    public function test_opening_a_period_closes_the_previously_open_one(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $old = PoPeriod::factory()->create(['status' => 'open']);
        $new = PoPeriod::factory()->create(['status' => 'draft']);

        $this->post("/admin/periods/{$new->id}/open")->assertRedirect('/admin/periods');

        $this->assertSame('open', $new->fresh()->status);
        $this->assertSame('closed', $old->fresh()->status);
    }

    public function test_closes_at_must_be_after_opens_at(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post('/admin/periods', [
            'name' => 'PO terbalik',
            'opens_at' => '2026-11-20 10:00:00',
            'closes_at' => '2026-11-15 20:00:00',
        ])->assertSessionHasErrors('closes_at');

        $this->assertDatabaseMissing('po_periods', ['name' => 'PO terbalik']);
    }
}
