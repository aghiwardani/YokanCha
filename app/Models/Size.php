<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Master list of bottle sizes ("250 ml", "500 ml", "1 liter"). Products store
 * the chosen size names in size_options; volume_ml orders them smallest first
 * and scales recipes when a new size is added to a product.
 */
class Size extends Model
{
    protected $fillable = [
        'name',
        'volume_ml',
        'extra_matcha_price',
        'extra_matcha_percent',
    ];

    protected $casts = [
        'volume_ml' => 'integer',
        'extra_matcha_price' => 'integer',
        'extra_matcha_percent' => 'integer',
    ];

    /**
     * Price added per bottle for "Extra matcha" in this size; 0 = not offered.
     */
    public static function extraMatchaPrice(?string $name): int
    {
        if ($name === null || $name === '' || $name === '-') {
            return 0;
        }

        return static::extraMatchaPrices([$name])[$name] ?? 0;
    }

    /**
     * Extra matcha price per size name, only sizes that offer it.
     *
     * @param  array<int,string>  $names
     * @return array<string,int>
     */
    public static function extraMatchaPrices(array $names): array
    {
        if ($names === []) {
            return [];
        }

        return static::whereIn('name', $names)->where('extra_matcha_price', '>', 0)
            ->pluck('extra_matcha_price', 'name')->map(fn ($price) => (int) $price)->all();
    }

    // Sizes with an unknown volume (0) go last.
    public function scopeSmallestFirst(Builder $query): Builder
    {
        return $query->orderByRaw('volume_ml = 0')->orderBy('volume_ml')->orderBy('name');
    }

    /**
     * Sort key for a volume: unknown (0) sorts after every real size.
     */
    public static function sortVolume(int $volume): int
    {
        return $volume > 0 ? $volume : PHP_INT_MAX;
    }

    /**
     * Best guess of the volume in ml from a label such as "250 ml", "250ml",
     * "1.000 ml", "1 liter", "1L" or "1.5 liter"; 0 when the label has no number.
     */
    public static function volumeFromName(string $name): int
    {
        if (! preg_match('/(\d{1,3}(?:\.\d{3})+|\d+(?:[.,]\d+)?)\s*([a-z]*)/i', $name, $m)) {
            return 0;
        }

        // "1.000" is Indonesian for one thousand; "1.5" is one and a half.
        $number = preg_match('/^\d{1,3}(?:\.\d{3})+$/', $m[1])
            ? (float) str_replace('.', '', $m[1])
            : (float) str_replace(',', '.', $m[1]);
        $unit = strtolower($m[2]);

        $isLiter = $unit !== '' && $unit[0] === 'l';

        return (int) round($isLiter ? $number * 1000 : $number);
    }

    /**
     * Give this size a new name and carry it into everything that refers to
     * it by name: products' size lists and prices, and recipes. Past orders
     * keep the name they were placed with. Returns how many products changed.
     * Call inside a transaction.
     */
    public function renameEverywhere(string $newName): int
    {
        $oldName = $this->name;
        $this->update(['name' => $newName]);

        if ($oldName === $newName) {
            return 0;
        }

        $touched = 0;

        foreach (Product::whereNotNull('size_options')->get() as $product) {
            $sizes = $product->size_options ?? [];
            if (! in_array($oldName, $sizes, true)) {
                continue;
            }

            $prices = $product->size_prices ?? [];
            if (array_key_exists($oldName, $prices)) {
                $prices[$newName] ??= $prices[$oldName];
                unset($prices[$oldName]);
            }

            $product->update([
                'size_options' => array_values(array_unique(array_map(fn ($size) => $size === $oldName ? $newName : $size, $sizes))),
                'size_prices' => $prices ?: null,
            ]);

            // A recipe row already saved under the new name wins over the old one.
            $taken = ProductRecipe::where('product_id', $product->id)->where('size', $newName)->pluck('ingredient_id');
            ProductRecipe::where('product_id', $product->id)->where('size', $oldName)->whereIn('ingredient_id', $taken)->delete();
            ProductRecipe::where('product_id', $product->id)->where('size', $oldName)->update(['size' => $newName]);

            $touched++;
        }

        return $touched;
    }

    /**
     * Whether any product currently sells this size.
     */
    public function isUsed(): bool
    {
        return Product::whereNotNull('size_options')
            ->get(['size_options'])
            ->contains(fn (Product $product) => in_array($this->name, $product->size_options ?? [], true));
    }
}
