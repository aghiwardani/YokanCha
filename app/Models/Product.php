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
    ];

    protected $casts = [
        'base_price' => 'integer',
        'size_options' => 'array',
        'size_prices' => 'array',
        'sweetness_options' => 'array',
        'sort_order' => 'integer',
    ];

    public function poPeriodProducts(): HasMany
    {
        return $this->hasMany(PoPeriodProduct::class);
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
