<?php

namespace Tests\Feature;

use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_products_for_open_period_with_remaining_quota(): void
    {
        $period = PoPeriod::create([
            'name' => 'PO Batch 1',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'status' => 'open',
        ]);

        $product = Product::create([
            'category' => 'minuman',
            'name' => 'Matcha Latte',
            'description' => 'Matcha premium dengan susu segar.',
            'base_price' => 25000,
            'size_options' => ['250ml', '1L'],
            'sweetness_options' => ['Normal', 'Less Sugar'],
        ]);

        PoPeriodProduct::create([
            'po_period_id' => $period->id,
            'product_id' => $product->id,
            'quota' => 5,
            'ordered_qty' => 2,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Matcha Latte');
        $response->assertSee('Sisa 3 botol');
        $response->assertSee('Stok terbatas');
    }

    public function test_shows_price_per_size_and_orders_by_sort_order(): void
    {
        $period = PoPeriod::create([
            'name' => 'PO Batch 1',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'status' => 'open',
        ]);

        $second = Product::create([
            'name' => 'Matcha Murni',
            'base_price' => 26000,
            'size_options' => ['250 ml', '1 liter'],
            'size_prices' => ['250 ml' => 26000, '1 liter' => 88000],
            'sweetness_options' => ['Manis normal'],
            'sort_order' => 2,
        ]);

        $first = Product::create([
            'name' => 'Maple Matcha Latte',
            'base_price' => 32000,
            'size_options' => ['250 ml', '1 liter'],
            'size_prices' => ['250 ml' => 32000, '1 liter' => 110000],
            'sweetness_options' => ['Manis normal'],
            'tag' => 'Maple Signature',
            'sort_order' => 1,
        ]);

        foreach ([$second, $first] as $product) {
            PoPeriodProduct::create([
                'po_period_id' => $period->id,
                'product_id' => $product->id,
                'quota' => 50,
            ]);
        }

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Maple Matcha Latte', 'Matcha Murni'])
            ->assertSee('Rp110.000')
            ->assertSee('Rp88.000')
            ->assertSee('Maple Signature')
            ->assertSee('2 varian');
    }

    public function test_header_cart_button_shows_bottle_count(): void
    {
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'open']);
        $product = Product::create(['name' => 'Matcha Oat', 'base_price' => 32000, 'size_options' => ['250 ml']]);
        $ppp = PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $product->id, 'quota' => 10]);

        $this->get('/')->assertSee('Keranjang')->assertDontSee('Keranjang · ');

        $this->post('/cart', ['po_period_product_id' => $ppp->id, 'qty' => 2, 'size' => '250 ml', 'sweetness' => '-']);

        $this->get('/')->assertSee('Keranjang · 2');
    }

    public function test_size_summary_lists_each_size_once_despite_spacing(): void
    {
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'open']);

        foreach ([['250ml'], ['250 ml', '1 liter']] as $index => $sizes) {
            $product = Product::create(['name' => "Produk {$index}", 'base_price' => 25000, 'size_options' => $sizes]);
            PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $product->id, 'quota' => 10]);
        }

        $this->get('/')->assertSee('2 varian · 250ml &amp; 1 liter', false);
    }

    public function test_landing_says_matcha_is_ceremonial_blend(): void
    {
        $this->get('/')
            ->assertSee('Matcha Ceremonial Blend')
            ->assertSee('ceremonial grade sama premium grade')
            ->assertDontSee('Ceremonial grade')
            ->assertSee('Matchanya pakai grade apa?');
    }

    public function test_faq_fits_selling_to_office_colleagues(): void
    {
        $this->get('/')
            ->assertSee('Botolnya diambil di mana?')
            ->assertDontSee('acara kantor')
            ->assertDontSee('luar kota')
            ->assertSee('Ambil di kantor')
            ->assertDontSee('Kirim besok')
            ->assertDontSee(' kami ')
            ->assertDontSee(', ya');
    }

    public function test_size_copy_defaults_to_the_three_sizes_sold(): void
    {
        $this->get('/')
            ->assertSee('Ada ukuran 250 ml, 500 ml, dan 1 liter')
            ->assertSee('ukuran 250 ml, 500 ml, atau 1 liter');
    }

    public function test_size_copy_follows_sizes_in_the_open_batch(): void
    {
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'open']);
        $product = Product::create(['name' => 'Matcha Oat', 'base_price' => 32000, 'size_options' => ['250 ml', '500 ml']]);
        PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $product->id, 'quota' => 10]);

        $this->get('/')
            ->assertSee('Ada ukuran 250 ml dan 500 ml')
            ->assertSee('ukuran 250 ml atau 500 ml')
            ->assertDontSee('1 liter');
    }

    public function test_footer_links_to_order_lookup(): void
    {
        $this->get('/')->assertSee('href="'.route('orders.lookup').'"', false);
    }

    public function test_order_deadline_follows_period_closing_time(): void
    {
        $this->travelTo(now()->setTime(9, 0));

        PoPeriod::create([
            'name' => 'PO',
            'status' => 'open',
            'opens_at' => now()->subHour(),
            'closes_at' => now()->setTime(21, 30),
        ]);

        $this->get('/')
            ->assertSee('Batas pesan 21.30')
            ->assertSee('pesan sebelum jam 21.30')
            ->assertDontSee('Batas pesan 20.00');
    }

    public function test_deadline_on_another_day_reads_naturally(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(9, 0));

        PoPeriod::create([
            'name' => 'PO',
            'status' => 'open',
            'opens_at' => now()->subHour(),
            'closes_at' => now()->setDate(2026, 11, 12)->setTime(20, 0),
        ]);

        $this->get('/')
            ->assertSee('pesan sebelum 12 Nov jam 20.00')
            ->assertSee('PO tutup 12 Nov jam 20.00');
    }

    public function test_shows_closed_state_when_no_open_period(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Pre-order lagi tutup dulu');
    }
}
