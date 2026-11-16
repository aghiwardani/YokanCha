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
    ];

    protected $casts = [
        'volume_ml' => 'integer',
    ];

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
     * Whether any product currently sells this size.
     */
    public function isUsed(): bool
    {
        return Product::whereNotNull('size_options')
            ->get(['size_options'])
            ->contains(fn (Product $product) => in_array($this->name, $product->size_options ?? [], true));
    }
}
