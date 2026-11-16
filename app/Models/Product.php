<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    public const TONES = ['amber', 'sage', 'oat'];

    public const BOTTLE_STYLES = ['klasik', 'maple', 'oat', 'murni', 'strawberry'];

    protected $fillable = [
        'category',
        'name',
        'description',
        'photo_path',
        'base_price',
        'size_options',
        'size_prices',
        'sweetness_options',
        'tag',
        'tone',
        'bottle_style',
        'sort_order',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $casts = [
        'base_price' => 'integer',
        'size_options' => 'array',
        'size_prices' => 'array',
        'sweetness_options' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function poPeriodProducts(): HasMany
    {
        return $this->hasMany(PoPeriodProduct::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(ProductRecipe::class);
    }

    /**
     * The product's sizes, smallest first by the size master's volume (sizes
     * missing from the master fall back to a volume guessed from the name).
     *
     * @return array<int, string>
     */
    public function sizesSmallestFirst(): array
    {
        $sizes = array_values($this->size_options ?? []);

        if (count($sizes) < 2) {
            return $sizes;
        }

        $volumes = Size::whereIn('name', $sizes)->pluck('volume_ml', 'name');
        $volumeOf = fn (string $size) => $volumes[$size] ?? Size::volumeFromName($size);

        usort($sizes, fn ($a, $b) => Size::sortVolume($volumeOf($a)) <=> Size::sortVolume($volumeOf($b)));

        return $sizes;
    }

    /**
     * Unit price for the given size label. Sizes without an explicit price
     * (or products without per-size pricing) use base_price.
     */
    public function priceFor(?string $size): int
    {
        $prices = $this->size_prices ?? [];

        if ($size !== null && isset($prices[$size])) {
            return (int) $prices[$size];
        }

        return $this->base_price;
    }
}
