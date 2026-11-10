<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'name',
        'description',
        'photo_path',
        'base_price',
        'size_options',
        'sweetness_options',
    ];

    protected $casts = [
        'base_price' => 'integer',
        'size_options' => 'array',
        'sweetness_options' => 'array',
    ];

    public function poPeriodProducts(): HasMany
    {
        return $this->hasMany(PoPeriodProduct::class);
    }
}
