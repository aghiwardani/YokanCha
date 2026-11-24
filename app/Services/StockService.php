<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\OrderItemIngredient;
use App\Models\PoPeriodIngredient;
use App\Models\PoPeriodProduct;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\SweetnessLevel;
use Illuminate\Support\Facades\DB;

class StockService
{
    /** @var array<string, int>|null sweetness name => percent, loaded once per service */
    private ?array $sweetnessPercents = null;

    /**
     * Percent of the Normal sugar amount used at this sweetness level.
     */
    public function sugarPercent(string $sweetness): int
    {
        $this->sweetnessPercents ??= SweetnessLevel::percentMap();

        return SweetnessLevel::percentFor($sweetness, $this->sweetnessPercents);
    }

    /**
     * Amount of each ingredient in one bottle. Recipes hold the Normal amount;
     * sugar ingredients are scaled by the sweetness level's percent.
     *
     * @return array<int,float> ingredient_id => amount per bottle (empty = no recipe)
     */
    public function perBottle(Product $product, string $size, string $sweetness): array
    {
        $percent = $this->sugarPercent($sweetness);
        $result = [];

        $recipes = ProductRecipe::query()
            ->where('product_id', $product->id)
            ->where('size', $size)
            ->with('ingredient')
            ->get();

        foreach ($recipes as $recipe) {
            $amount = (float) $recipe->amount;
            if ($recipe->ingredient?->is_sugar) {
                $amount = round($amount * $percent / 100, 2);
            }
            // "Tanpa gula" needs none of that sweetener at all.
            if ($amount > 0) {
                $result[$recipe->ingredient_id] = $amount;
            }
        }

        return $result;
    }

    /**
     * @return array<int,float> ingredient_id => stock - used
     */
    public function remainingFor(int $poPeriodId): array
    {
        $result = [];

        PoPeriodIngredient::query()->where('po_period_id', $poPeriodId)->get()
            ->each(function (PoPeriodIngredient $row) use (&$result) {
                $result[$row->ingredient_id] = $row->remaining();
            });

        return $result;
    }

    /**
     * @param array<int,float> $alreadyNeeded ingredient_id => amount
     */
    public function available(PoPeriodProduct $ppp, string $size, string $sweetness, array $alreadyNeeded = []): int
    {
        $perBottle = $this->perBottle($ppp->product, $size, $sweetness);
        if ($perBottle === []) {
            return 0;
        }

        $remaining = $this->remainingFor($ppp->po_period_id);
        $best = null;

        foreach ($perBottle as $ingredientId => $amount) {
            if (! array_key_exists($ingredientId, $remaining)) {
                return 0;
            }
            $per = $this->hundredths($amount);
            if ($per <= 0) {
                continue;
            }
            $left = $this->hundredths($remaining[$ingredientId])
                - $this->hundredths($alreadyNeeded[$ingredientId] ?? 0);
            $count = $left <= 0 ? 0 : intdiv($left, $per);
            $best = $best === null ? $count : min($best, $count);
        }

        return $best ?? 0;
    }

    public function maxAvailable(PoPeriodProduct $ppp): int
    {
        $sizes = $ppp->product->size_options;
        $sweets = $ppp->product->sweetness_options;
        $sizes = empty($sizes) ? ['-'] : array_values($sizes);
        $sweets = empty($sweets) ? ['-'] : array_values($sweets);

        $max = 0;
        foreach ($sizes as $size) {
            foreach ($sweets as $sweet) {
                $max = max($max, $this->available($ppp, (string) $size, (string) $sweet));
            }
        }

        return $max;
    }

    /**
     * @param array<int,array{po_period_product:PoPeriodProduct,size:string,sweetness:string,qty:int}> $lines
     * @return array<int,float>
     */
    public function need(array $lines): array
    {
        $need = [];
        foreach ($lines as $line) {
            foreach ($this->lineUsage($line) as $ingredientId => $amount) {
                $need[$ingredientId] = ($need[$ingredientId] ?? 0) + $amount;
            }
        }

        return $need;
    }

    /**
     * Must run inside the caller's transaction.
     *
     * @return array<int,array<int,float>> line index => (ingredient_id => usage)
     */
    public function reserve(int $poPeriodId, array $lines, bool $allowShort = false): array
    {
        // $allowShort: an order the admin enters by hand may go over the stock
        // (the bottles are already made); "used" then simply ends up above stock.
        return DB::transaction(function () use ($poPeriodId, $lines, $allowShort) {
            $rows = PoPeriodIngredient::query()
                ->where('po_period_id', $poPeriodId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('ingredient_id');

            $usages = [];
            $running = []; // ingredient_id => hundredths
            foreach ($lines as $index => $line) {
                $usage = $this->lineUsage($line);
                if ($usage === [] && ! $allowShort) {
                    throw new InsufficientStockException($line['po_period_product']->product->name);
                }
                foreach ($usage as $ingredientId => $amount) {
                    $running[$ingredientId] = ($running[$ingredientId] ?? 0) + $this->hundredths($amount);
                    $row = $rows->get($ingredientId);
                    $short = $row === null || $running[$ingredientId] > $this->hundredths($row->remaining());
                    if ($short && ! $allowShort) {
                        throw new InsufficientStockException($line['po_period_product']->product->name);
                    }
                }
                $usages[$index] = $usage;
            }

            foreach ($running as $ingredientId => $hundredths) {
                // Over-stock admin orders may use an ingredient the period has no row for.
                $row = $rows->get($ingredientId) ?? PoPeriodIngredient::create([
                    'po_period_id' => $poPeriodId, 'ingredient_id' => $ingredientId, 'stock' => 0, 'used' => 0,
                ]);
                $row->used = ($this->hundredths($row->used) + $hundredths) / 100;
                $row->save();
            }

            return $usages;
        });
    }

    public function release(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $totals = OrderItemIngredient::query()
                ->whereIn('order_item_id', $order->items()->select('id'))
                ->get()
                ->groupBy('ingredient_id')
                ->map(fn ($g) => $g->sum(fn ($u) => $this->hundredths($u->amount)));

            if ($totals->isEmpty()) {
                return false;
            }

            $rows = PoPeriodIngredient::query()
                ->where('po_period_id', $order->po_period_id)
                ->whereIn('ingredient_id', $totals->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $new = max(0, $this->hundredths($row->used) - $totals[$row->ingredient_id]);
                $row->used = $new / 100;
                $row->save();
            }

            return true;
        });
    }

    /** @return array<int,float> */
    private function lineUsage(array $line): array
    {
        $per = $this->perBottle($line['po_period_product']->product, $line['size'], $line['sweetness']);
        $usage = [];
        foreach ($per as $ingredientId => $amount) {
            $usage[$ingredientId] = $this->hundredths($amount) * (int) $line['qty'] / 100;
        }

        return $usage;
    }

    private function hundredths(float|int|string $x): int
    {
        return (int) round(((float) $x) * 100);
    }
}
