<?php

namespace Tests\Feature;

use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StocksMenu;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;
    use StocksMenu;

    public function test_shows_products_for_open_period_with_remaining_stock(): void
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

        $milk = $this->ingredient('Susu');
        $this->stock($period, $milk, 600);
        $this->recipe($product, '250ml', $milk, 200);
        $this->recipe($product, '1L', $milk, 1000);

        PoPeriodProduct::create([
            'po_period_id' => $period->id,
            'product_id' => $product->id,
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

        $milk = $this->ingredient('Susu');
        $this->stock($period, $milk, 100000);

        foreach ([$second, $first] as $product) {
            $this->recipe($product, '250 ml', $milk, 200);
            $this->recipe($product, '1 liter', $milk, 800);
            PoPeriodProduct::create([
                'po_period_id' => $period->id,
                'product_id' => $product->id,
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
        $milk = $this->ingredient('Susu');
        $this->stock($period, $milk, 10000);
        $this->recipe($product, '250 ml', $milk, 200);
        $ppp = PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $product->id]);

        $this->get('/')->assertSee('Keranjang')->assertDontSee('Keranjang · ');

        $this->post('/cart', ['po_period_product_id' => $ppp->id, 'qty' => 2, 'size' => '250 ml', 'sweetness' => '-']);

        $this->get('/')->assertSee('Keranjang · 2');
    }

    public function test_size_summary_lists_each_size_once_despite_spacing(): void
    {
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'open']);

        foreach ([['250ml'], ['250 ml', '1 liter']] as $index => $sizes) {
            $product = Product::create(['name' => "Produk {$index}", 'base_price' => 25000, 'size_options' => $sizes]);
            PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $product->id]);
        }

        $this->get('/')->assertSee('2 varian · 250ml &amp; 1 liter', false);
    }

    public function test_card_shows_habis_when_an_ingredient_runs_out(): void
    {
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'open']);
        $product = Product::create(['name' => 'Matcha Oat', 'base_price' => 32000, 'size_options' => ['250 ml']]);
        $milk = $this->ingredient('Susu');
        $this->stock($period, $milk, 1000, 900);
        $this->recipe($product, '250 ml', $milk, 200);
        PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $product->id]);

        $this->get('/')
            ->assertSee('Bahan untuk menu ini sudah habis di batch ini.')
            ->assertSee('>Stok habis</button>', false)
            ->assertSee('Sisa 0 botol');
    }

    public function test_bottles_in_own_cart_are_taken_off_what_can_still_be_added(): void
    {
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'open']);
        $product = Product::create(['name' => 'Matcha Oat', 'base_price' => 32000, 'size_options' => ['250 ml']]);
        $milk = $this->ingredient('Susu');
        $this->stock($period, $milk, 600);
        $this->recipe($product, '250 ml', $milk, 200);
        $ppp = PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $product->id]);

        $this->post('/cart', ['po_period_product_id' => $ppp->id, 'qty' => 2, 'size' => '250 ml', 'sweetness' => '-']);
        $this->get('/')->assertSee('>Tambah</button>', false)->assertSee('max="1"', false);

        $response = $this->postJson('/cart', ['po_period_product_id' => $ppp->id, 'qty' => 1, 'size' => '250 ml', 'sweetness' => '-']);
        $response->assertOk()->assertJsonPath("availability.{$ppp->id}.cells.250 ml.-", 0);

        $this->get('/')->assertSee('>Stok habis</button>', false);
    }

    public function test_cart_does_not_reserve_stock_for_other_visitors(): void
    {
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'open']);
        $product = Product::create(['name' => 'Matcha Oat', 'base_price' => 32000, 'size_options' => ['250 ml']]);
        $milk = $this->ingredient('Susu');
        $this->stock($period, $milk, 600);
        $this->recipe($product, '250 ml', $milk, 200);
        $ppp = PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $product->id]);

        $this->post('/cart', ['po_period_product_id' => $ppp->id, 'qty' => 3, 'size' => '250 ml', 'sweetness' => '-']);

        // Another visitor (fresh session) still sees all 3 bottles.
        $this->flushSession();
        $this->get('/')->assertSee('Sisa 3 botol')->assertSee('>Tambah</button>', false);
    }

    public function test_size_without_enough_stock_is_disabled(): void
    {
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'open']);
        $product = Product::create([
            'name' => 'Matcha Oat',
            'base_price' => 32000,
            'size_options' => ['250 ml', '1 liter'],
            'size_prices' => ['250 ml' => 32000, '1 liter' => 110000],
        ]);
        $milk = $this->ingredient('Susu');
        $this->stock($period, $milk, 500);
        $this->recipe($product, '250 ml', $milk, 1000);
        $this->recipe($product, '1 liter', $milk, 250);
        PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $product->id]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/value="250 ml"[^>]*disabled/s', $html);
        $this->assertDoesNotMatchRegularExpression('/value="1 liter"[^>]*disabled/s', $html);
        $this->assertMatchesRegularExpression('/value="1 liter"[^>]*checked/s', $html);
        $this->assertDoesNotMatchRegularExpression('/value="250 ml"[^>]*checked/s', $html);
    }

    public function test_sizes_show_smallest_first_and_smallest_is_preselected(): void
    {
        Size::create(['name' => '250 ml', 'volume_ml' => 250]);
        Size::create(['name' => '500 ml', 'volume_ml' => 500]);
        Size::create(['name' => '1 liter', 'volume_ml' => 1000]);
        $period = PoPeriod::create(['name' => 'PO', 'status' => 'open']);
        // Saved out of order, e.g. before the size master existed.
        $product = Product::create([
            'name' => 'Matcha Oat',
            'base_price' => 32000,
            'size_options' => ['1 liter', '500 ml', '250 ml'],
        ]);
        $milk = $this->ingredient('Susu');
        $this->stock($period, $milk, 5000);
        foreach (['250 ml' => 200, '500 ml' => 400, '1 liter' => 800] as $size => $amount) {
            $this->recipe($product, $size, $milk, $amount);
        }
        PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $product->id]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/value="250 ml".*value="500 ml".*value="1 liter"/s', $html);
        $this->assertMatchesRegularExpression('/value="250 ml"[^>]*checked/s', $html);
        $this->assertDoesNotMatchRegularExpression('/value="1 liter"[^>]*checked/s', $html);
    }

    public function test_landing_says_matcha_is_ceremonial_blend(): void
    {
        $this->get('/')
            ->assertSee('Matcha Ceremonial Blend')
            ->assertSee('ceremonial grade sama premium grade')
            ->assertDontSee('Ceremonial grade')
            ->assertSee('Matchanya pakai grade apa?');
    }

    public function test_milk_supplier_stays_secret(): void
    {
        $this->get('/')
            ->assertSee('Signature Milk')
            ->assertDontSee('D.O.A');
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
        $milk = $this->ingredient('Susu');
        $this->stock($period, $milk, 10000);
        $this->recipe($product, '250 ml', $milk, 200);
        PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $product->id]);

        $this->get('/')
            ->assertSee('Ada ukuran 250 ml dan 500 ml')
            ->assertSee('ukuran 250 ml atau 500 ml')
            ->assertDontSee('1 liter');
    }

    public function test_pages_have_the_mascot_as_tab_icon(): void
    {
        $this->get('/')
            ->assertSee('rel="icon" href="'.asset('favicon.ico').'"', false)
            ->assertSee('rel="apple-touch-icon"', false);

        foreach (['favicon.ico', 'favicon-32.png', 'apple-touch-icon.png', 'icon-192.png', 'icon-512.png'] as $file) {
            $this->assertGreaterThan(0, filesize(public_path($file)), "{$file} is empty");
        }
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

    public function test_closed_po_still_shows_the_menu_but_cannot_order(): void
    {
        Product::create([
            'name' => 'Maple Signature', 'base_price' => 32000,
            'size_options' => ['250 ml', '1 liter'], 'size_prices' => ['250 ml' => 32000, '1 liter' => 110000],
            'sweetness_options' => ['Less Sugar', 'Normal'],
        ]);
        Product::create(['name' => 'Menu Lama', 'base_price' => 20000, 'is_active' => false]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Pre-order lagi tutup dulu.')
            ->assertSee('Menu YokanCha')
            ->assertSee('Maple Signature')
            ->assertSee('Rp110.000')
            ->assertSeeInOrder(['Less Sugar', 'Normal'])
            ->assertSee('Pesan saat PO buka')
            ->assertDontSee('Menu Lama')
            ->assertDontSee('data-cart-add', false)
            ->assertDontSee('>Tambah</button>', false);
    }

    public function test_sweetness_preselects_makeable_level_and_disables_unmakeable(): void
    {
        $period = PoPeriod::create([
            'name' => 'PO', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'status' => 'open',
        ]);
        $product = Product::create([
            'category' => 'minuman', 'name' => 'Matcha Latte', 'base_price' => 25000,
            'size_options' => ['250ml'], 'sweetness_options' => ['Manis normal', 'Less sugar'],
        ]);
        $milk = $this->ingredient('Susu');
        $sugar = $this->ingredient('Gula', 'g', true);
        $this->stock($period, $milk, 1000);
        $this->stock($period, $sugar, 10);
        $this->recipe($product, '250ml', $milk, 200);
        $this->recipe($product, '250ml', $sugar, 20, 8);
        PoPeriodProduct::create(['po_period_id' => $period->id, 'product_id' => $product->id]);

        $html = $this->get('/')->assertOk()->getContent();

        preg_match('/<input[^>]*value="Manis normal"[^>]*>/s', $html, $normal);
        preg_match('/<input[^>]*value="Less sugar"[^>]*>/s', $html, $less);

        $this->assertStringContainsString('disabled', $normal[0]);
        $this->assertStringNotContainsString('checked', $normal[0]);
        $this->assertStringContainsString('checked', $less[0]);
        $this->assertStringNotContainsString('disabled', $less[0]);
    }
}
