<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StocksMenu;
use Tests\TestCase;

class OrderThemeTest extends TestCase
{
    use RefreshDatabase;
    use StocksMenu;

    private PoPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->period = PoPeriod::create(['name' => 'PO', 'status' => 'open']);
    }

    private function menuItem(string $name, string $category): PoPeriodProduct
    {
        $product = Product::create(['name' => $name, 'category' => $category, 'base_price' => 20000]);
        $milk = $this->ingredient("Bahan {$name}");
        $this->recipe($product, '-', $milk, 100);
        $this->stock($this->period, $milk, 10000);

        return PoPeriodProduct::create(['po_period_id' => $this->period->id, 'product_id' => $product->id]);
    }

    private function addToCart(PoPeriodProduct $ppp): void
    {
        $this->post(route('cart.add'), ['po_period_product_id' => $ppp->id, 'qty' => 1, 'size' => '-', 'sweetness' => '-'])
            ->assertSessionHasNoErrors();
    }

    public function test_theme_follows_what_is_in_the_cart(): void
    {
        $this->get(route('cart.show'))->assertSee('data-theme="matcha"', false);

        $this->addToCart($this->menuItem('Kopi Susu Aren', 'coffee'));
        $this->get(route('cart.show'))->assertSee('data-theme="coffee"', false)->assertSee('#5C3A26', false);
        $this->get(route('checkout.show'))->assertSee('data-theme="coffee"', false);

        $this->addToCart($this->menuItem('Ceremonial Latte', 'matcha'));
        $this->get(route('cart.show'))->assertSee('data-theme="blend"', false);
        $this->get(route('checkout.show'))->assertSee('data-theme="blend"', false);
    }

    public function test_confirmation_keeps_the_theme_of_the_order(): void
    {
        $this->addToCart($this->menuItem('Kopi Susu Aren', 'coffee'));
        $this->post(route('checkout.store'), ['customer_name' => 'Rina', 'customer_whatsapp' => '081234567890']);
        $order = Order::firstOrFail();

        $this->get(route('checkout.confirmation', $order->order_number))
            ->assertOk()
            ->assertSee('data-theme="coffee"', false);

        $this->post(route('orders.lookup.search'), ['order_number' => $order->order_number, 'customer_whatsapp' => '081234567890'])
            ->assertOk()
            ->assertSee('data-theme="coffee"', false);
    }

    public function test_mascot_follows_the_theme_off_the_menu(): void
    {
        $this->assertSame('matcha', Product::themeFor([]));
        $this->assertSame('blend', Product::themeFor([
            new Product(['category' => 'coffee']), new Product(['category' => 'matcha']),
        ]));

        // Menu carries both mascots so the Coffee tab can swap them in place.
        $this->menuItem('Ceremonial Latte', 'matcha');
        $this->menuItem('Kopi Susu Aren', 'coffee');
        $this->get(route('menu.index'))->assertSee('yoka-coffee-hero', false)->assertSee('yoka-hero.png', false);
    }
}
