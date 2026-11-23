<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemIngredient;
use App\Models\PoPeriod;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StocksMenu;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;
    use StocksMenu;

    private function order(PoPeriod $period, string $name, string $payment, string $status, Product $product, string $size, string $sweetness, int $qty, int $price, array $usage): Order
    {
        $order = Order::create([
            'po_period_id' => $period->id, 'customer_name' => $name, 'customer_whatsapp' => '081234567890',
            'payment_status' => $payment, 'order_status' => $status, 'order_number' => 'YC-'.uniqid(),
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'size' => $size, 'sweetness' => $sweetness,
            'qty' => $qty, 'unit_price' => $price,
        ]);
        foreach ($usage as [$ingredient, $perBottle]) {
            OrderItemIngredient::create(['order_item_id' => $item->id, 'ingredient_id' => $ingredient->id, 'amount' => $perBottle * $qty]);
        }

        return $order;
    }

    /**
     * Batch with Rp20/ml milk, two products, one buyer ordering twice and a cancelled order.
     */
    private function batch(): PoPeriod
    {
        $period = PoPeriod::create(['name' => 'PO Jumat', 'status' => 'closed', 'opens_at' => now()->subDays(2), 'closes_at' => now()->subDay()]);
        $maple = Product::create(['name' => 'Maple Signature', 'base_price' => 32000, 'size_options' => ['250 ml', '1 liter']]);
        $clear = Product::create(['name' => 'Clear Matcha', 'base_price' => 20000, 'size_options' => ['250 ml']]);
        $milk = $this->ingredient('Fresh Milk');
        $this->stock($period, $milk, 3000, 1600)->update(['cost' => 60000]);
        $period->costs()->create(['name' => 'Es batu', 'type' => 'per_batch', 'amount' => 10000]);

        // Maple 250 ml: 3 bottles (Nui x2 orders) ; Maple 1 liter: 1 ; Clear: 1
        $this->order($period, 'Nui', 'paid', 'selesai', $maple, '250 ml', 'Normal', 2, 32000, [[$milk, 200]]);
        $this->order($period, 'nui ', 'unpaid', 'new', $maple, '250 ml', 'Less Sugar', 1, 32000, [[$milk, 200]]);
        $this->order($period, 'Santi', 'paid', 'new', $maple, '1 liter', 'Normal', 1, 110000, [[$milk, 800]]);
        $this->order($period, 'Rina', 'paid', 'new', $clear, '250 ml', '-', 1, 20000, [[$milk, 200]]);
        $this->order($period, 'Batal', 'unpaid', 'cancelled', $clear, '250 ml', '-', 9, 20000, [[$milk, 200]]);

        return $period;
    }

    public function test_recap_lists_every_period_with_totals(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->batch();
        PoPeriod::create(['name' => 'PO Kosong', 'status' => 'draft']);

        // Sales 64.000 + 32.000 + 110.000 + 20.000 = 226.000
        // HPP: milk 1.600 ml x Rp20 = 32.000 + es 10.000 = 42.000 -> untung kotor 184.000 (81%)
        // Untung bersih: 226.000 - 60.000 belanja - 10.000 es = 156.000
        $this->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('Semua periode (2)')
            ->assertSeeInOrder(['PO Kosong', 'PO Jumat'])
            ->assertSee('Rp226.000')
            ->assertSee('Rp42.000')
            ->assertSee('Rp184.000')
            ->assertSee('Margin 81%')
            ->assertSee('Rp156.000')
            ->assertSee('Rp32.000 belum dibayar')
            ->assertSee('Terlaris:')
            ->assertSee('Maple Signature')
            ->assertSee('1 pesanan dibatalkan')
            ->assertSee('Belum ada pesanan.');
    }

    public function test_period_report_breaks_sales_down(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $period = $this->batch();

        $this->get(route('admin.reports.show', $period))
            ->assertOk()
            ->assertSee('Laporan PO Jumat')
            ->assertSee('4 pesanan, 5 botol, 1 dibatalkan (tidak dihitung)')
            ->assertSeeInOrder(['Penjualan per menu', 'Maple Signature', 'Clear Matcha'])
            ->assertSeeInOrder(['Per ukuran', '250 ml', '1 liter'])
            ->assertSeeInOrder(['Per tingkat manis', 'Normal'])
            // Nui ordered twice (name typed differently): one buyer, 2 orders, 3 bottles, Rp96.000.
            ->assertSeeInOrder(['Pembeli', 'Santi', 'Nui', 'Rina'])
            ->assertSee('Rp96.000')
            ->assertSee('Es batu (per batch)')
            ->assertSee('Pemakaian bahan')
            ->assertDontSee('Batal');
    }

    public function test_csv_export_has_one_row_per_period(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->batch();

        $response = $this->get(route('admin.reports.csv'));
        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $lines = array_values(array_filter(explode("\n", ltrim($response->streamedContent(), "\xEF\xBB\xBF"))));
        $this->assertCount(2, $lines);
        $this->assertStringStartsWith('Periode;Mulai;Tutup', $lines[0]);
        $this->assertStringContainsString('"PO Jumat"', $lines[1]);
        $this->assertStringContainsString(';226000;194000;32000;42000;184000;81;60000;10000;156000;', $lines[1]);
    }

    public function test_reports_page_is_empty_friendly_and_admin_only(): void
    {
        $this->get(route('admin.reports'))->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.reports'))->assertOk()->assertSee('Belum ada periode.');
    }
}
