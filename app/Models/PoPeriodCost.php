<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoPeriodCost extends Model
{
    public const PER_BOTTLE = 'per_bottle';

    public const PER_BATCH = 'per_batch';

    protected $fillable = [
        'po_period_id',
        'name',
        'type',
        'size',
        'amount',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function poPeriod(): BelongsTo
    {
        return $this->belongsTo(PoPeriod::class);
    }

    public function appliesToSize(string $size): bool
    {
        return $this->type === self::PER_BOTTLE && ($this->size === null || $this->size === $size);
    }
}
