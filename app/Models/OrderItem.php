<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'size',
        'sweetness',
        'extra_matcha',
        'qty',
        'unit_price',
    ];

    protected $casts = [
        'qty' => 'integer',
        'unit_price' => 'integer',
        'extra_matcha' => 'boolean',
    ];

    /**
     * "250 ml, Less Sugar, Extra matcha" (options of products without them are "-" and left out).
     */
    public function variantLabel(): string
    {
        return collect([$this->size, $this->sweetness, $this->extra_matcha ? 'Extra matcha' : null])
            ->filter(fn ($value) => $value && $value !== '-')
            ->implode(', ');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function ingredientUsages(): HasMany
    {
        return $this->hasMany(OrderItemIngredient::class);
    }
}
