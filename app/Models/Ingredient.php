<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'unit',
        'is_sugar',
        'is_matcha',
        'is_water',
        'is_milk',
        'pack_size',
        'pack_price',
    ];

    protected $casts = [
        'is_sugar' => 'boolean',
        'is_matcha' => 'boolean',
        'is_water' => 'boolean',
        'is_milk' => 'boolean',
        'pack_size' => 'decimal:2',
        'pack_price' => 'integer',
    ];

    /**
     * Master price per unit (per ml / per g), or null when none is set.
     */
    public function unitPrice(): ?float
    {
        if ($this->pack_price === null || (float) $this->pack_size <= 0) {
            return null;
        }

        return $this->pack_price / (float) $this->pack_size;
    }
}
