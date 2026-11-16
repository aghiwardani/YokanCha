<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Master list of sweetness levels ("Tanpa gula" 0%, "Less sugar" 50%,
 * "Manis normal" 100%, "Extra manis" 150%). Products store the chosen level
 * names in sweetness_options; percent scales every sugar ingredient of a
 * recipe (the recipe holds the Normal = 100% amount).
 */
class SweetnessLevel extends Model
{
    protected $fillable = [
        'name',
        'percent',
    ];

    protected $casts = [
        'percent' => 'integer',
    ];

    public function scopeLeastSweetFirst(Builder $query): Builder
    {
        return $query->orderBy('percent')->orderBy('name');
    }

    /**
     * name => percent for every level in the master list.
     *
     * @return array<string, int>
     */
    public static function percentMap(): array
    {
        return static::query()->pluck('percent', 'name')->map(fn ($percent) => (int) $percent)->all();
    }

    /**
     * Percent of the Normal sugar amount for a level name. Names missing
     * from the master (or "-" for products without levels) fall back to a
     * guess from the wording, so older data keeps working.
     *
     * @param  array<string, int>|null  $map  a percentMap() already loaded
     */
    public static function percentFor(string $name, ?array $map = null): int
    {
        $map ??= static::percentMap();

        return $map[$name] ?? self::percentFromName($name);
    }

    public static function percentFromName(string $name): int
    {
        $text = mb_strtolower(trim($name));

        return match (true) {
            str_contains($text, 'tanpa') || str_contains($text, 'no sugar') || str_contains($text, 'zero') => 0,
            str_contains($text, 'less') || str_contains($text, 'kurang') || str_contains($text, 'sedikit') => 50,
            str_contains($text, 'extra') || str_contains($text, 'ekstra') || str_contains($text, 'lebih') => 150,
            default => 100,
        };
    }

    /**
     * Give this level a new name and carry it into the products that offer
     * it. Past orders keep the name they were placed with. Returns how many
     * products changed. Call inside a transaction.
     */
    public function renameEverywhere(string $newName): int
    {
        $oldName = $this->name;
        $this->update(['name' => $newName]);

        if ($oldName === $newName) {
            return 0;
        }

        $touched = 0;

        foreach (Product::whereNotNull('sweetness_options')->get() as $product) {
            $levels = $product->sweetness_options ?? [];
            if (! in_array($oldName, $levels, true)) {
                continue;
            }

            $product->update([
                'sweetness_options' => array_values(array_unique(array_map(fn ($level) => $level === $oldName ? $newName : $level, $levels))),
            ]);
            $touched++;
        }

        return $touched;
    }

    /**
     * Whether any product currently offers this level.
     */
    public function isUsed(): bool
    {
        return Product::whereNotNull('sweetness_options')
            ->get(['sweetness_options'])
            ->contains(fn (Product $product) => in_array($this->name, $product->sweetness_options ?? [], true));
    }
}
