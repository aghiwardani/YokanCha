<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PoPeriod;
use App\Models\PoPeriodProduct;
use App\Support\Number;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Orders the admin types in by hand, e.g. a colleague who asked after the PO
 * closed. Works for any period (open or closed) and takes ingredients from
 * that period's stock like a normal checkout.
 */
class ManualOrderController extends Controller
{
    public function __construct(private \App\Services\StockService $stock)
    {
    }

    public function create(Request $request): View
    {
        $periods = PoPeriod::orderByDesc('created_at')->orderByDesc('id')->get();
        $period = $periods->firstWhere('id', (int) $request->query('period')) ?? $periods->first();

        return view('admin.orders.create', [
            'periods' => $periods,
            'period' => $period,
            'menu' => $period ? $this->menuFor($period) : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['lines' => collect((array) $request->input('lines', []))
            ->map(fn ($line) => is_array($line)
                ? array_merge($line, ['qty' => Number::normalize($line['qty'] ?? null), 'price' => Number::normalize($line['price'] ?? null)])
                : $line)
            ->all()]);

        $data = $request->validate([
            'po_period_id' => ['required', 'integer', 'exists:po_periods,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_whatsapp' => ['required', 'string', 'max:30', function ($attribute, $value, $fail) {
                if (! Phone::looksValid((string) $value)) {
                    $fail('Nomor WhatsApp belum benar. Contoh: 081234567890.');
                }
            }],
            'payment_status' => ['required', 'in:paid,unpaid'],
            'order_status' => ['required', 'in:new,diproses,siap_diambil,selesai'],
            'allow_short' => ['nullable', 'boolean'],
            'lines' => ['required', 'array'],
            'lines.*.ppp' => ['nullable', 'integer'],
            'lines.*.size' => ['nullable', 'string', 'max:100'],
            'lines.*.sweetness' => ['nullable', 'string', 'max:100'],
            'lines.*.qty' => ['nullable', 'integer', 'min:1', 'max:999'],
            'lines.*.price' => ['nullable', 'integer', 'min:0', 'max:99999999'],
        ], [
            'lines.required' => 'Tambahkan minimal satu menu.',
        ], [
            'customer_name' => 'nama pemesan',
            'customer_whatsapp' => 'nomor WhatsApp',
        ]);

        $period = PoPeriod::findOrFail($data['po_period_id']);
        $lines = $this->lines($period, $data['lines']);

        $order = DB::transaction(function () use ($data, $period, $lines) {
            try {
                $usages = $this->stock->reserve($period->id, $lines, (bool) ($data['allow_short'] ?? false));
            } catch (InsufficientStockException $e) {
                throw ValidationException::withMessages([
                    'allow_short' => "Stok bahan buat {$e->productName} di periode ini tidak cukup (atau resepnya belum diisi). "
                        .'Centang "Tetap simpan walau stok kurang" kalau botolnya memang sudah dibuat.',
                ]);
            }

            $order = Order::create([
                'po_period_id' => $period->id,
                'customer_name' => trim($data['customer_name']),
                'customer_whatsapp' => trim($data['customer_whatsapp']),
                'payment_status' => $data['payment_status'],
                'order_status' => $data['order_status'],
                'order_number' => Order::newNumber($period->id),
                'source' => 'admin',
            ]);

            foreach ($lines as $index => $line) {
                $item = $order->items()->create([
                    'product_id' => $line['product']->id,
                    'size' => $line['size'],
                    'sweetness' => $line['sweetness'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                ]);
                foreach ($usages[$index] ?? [] as $ingredientId => $amount) {
                    $item->ingredientUsages()->create(['ingredient_id' => $ingredientId, 'amount' => $amount]);
                }
            }

            return $order;
        });

        return redirect()->route('admin.orders.show', $order)
            ->with('status', "Pesanan {$order->customer_name} ditambahkan ({$order->order_number}).");
    }

    /**
     * Menu of a period for the form: each product with its sizes (and price
     * per size) and sweetness levels.
     *
     * @return list<array{ppp:int, name:string, active:bool, sizes:array<string,int>, sweetness:list<string>}>
     */
    private function menuFor(PoPeriod $period): array
    {
        return PoPeriodProduct::where('po_period_id', $period->id)->with('product')->get()
            ->filter(fn (PoPeriodProduct $ppp) => $ppp->product)
            ->sortBy(fn (PoPeriodProduct $ppp) => [$ppp->product->sort_order, $ppp->product->name])
            ->map(function (PoPeriodProduct $ppp) {
                $product = $ppp->product;
                $sizes = $product->sizesSmallestFirst() ?: ['-'];

                return [
                    'ppp' => $ppp->id,
                    'name' => $product->name,
                    'active' => (bool) $product->is_active,
                    'sizes' => collect($sizes)->mapWithKeys(fn ($s) => [$s => $product->priceFor($s === '-' ? null : $s)])->all(),
                    'sweetness' => array_keys($product->sweetnessLeastFirst()) ?: ['-'],
                ];
            })->values()->all();
    }

    /**
     * Form rows -> the line shape StockService::reserve expects. Fully blank
     * rows are skipped; anything half-filled or not on this period's menu is an error.
     */
    private function lines(PoPeriod $period, array $rows): array
    {
        $ppps = PoPeriodProduct::where('po_period_id', $period->id)->with('product')->get()->keyBy('id');
        $lines = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $pppId = (int) ($row['ppp'] ?? 0);
            if ($pppId === 0 && empty($row['qty'])) {
                continue;
            }

            $ppp = $ppps->get($pppId);
            if (! $ppp || ! $ppp->product) {
                $errors["lines.{$i}.ppp"] = 'Pilih menu dari periode ini.';
                continue;
            }
            $product = $ppp->product;
            $sizes = $product->size_options ?: ['-'];
            $sweets = $product->sweetness_options ?: ['-'];
            $size = (string) ($row['size'] ?? '-');
            $sweetness = (string) ($row['sweetness'] ?? '-');

            if (! in_array($size, $sizes, true)) {
                $errors["lines.{$i}.size"] = "Pilih ukuran {$product->name}.";
            }
            if (! in_array($sweetness, $sweets, true)) {
                $errors["lines.{$i}.sweetness"] = "Pilih tingkat manis {$product->name}.";
            }
            if (empty($row['qty'])) {
                $errors["lines.{$i}.qty"] = "Isi jumlah botol {$product->name}.";
            }

            $price = ($row['price'] ?? null) === null || $row['price'] === ''
                ? $product->priceFor($size === '-' ? null : $size)
                : (int) $row['price'];

            $lines[] = [
                'po_period_product' => $ppp,
                'product' => $product,
                'size' => $size,
                'sweetness' => $sweetness,
                'qty' => (int) ($row['qty'] ?? 0),
                'unit_price' => $price,
            ];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'Tambahkan minimal satu menu.']);
        }

        return $lines;
    }
}
