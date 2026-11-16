<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoPeriodProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_period_id',
        'product_id',
    ];

    public function poPeriod(): BelongsTo
    {
        return $this->belongsTo(PoPeriod::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
