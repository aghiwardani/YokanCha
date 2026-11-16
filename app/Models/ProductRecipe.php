<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'size',
        'ingredient_id',
        'amount',
        'amount_less_sugar',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_less_sugar' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
