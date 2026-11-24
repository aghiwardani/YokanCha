<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_period_id',
        'customer_name',
        'customer_whatsapp',
        'payment_status',
        'order_status',
        'order_number',
        'source',
    ];

    protected $casts = [
        'payment_status' => 'string',
        'order_status' => 'string',
    ];

    public function poPeriod(): BelongsTo
    {
        return $this->belongsTo(PoPeriod::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function total(): int
    {
        return (int) $this->items->sum(fn (OrderItem $item) => $item->unit_price * $item->qty);
    }

    /**
     * A fresh, unused order number such as YC-3-K7Q2MX.
     */
    public static function newNumber(int $poPeriodId): string
    {
        do {
            $candidate = sprintf('YC-%d-%s', $poPeriodId, Str::upper(Str::random(6)));
        } while (static::where('order_number', $candidate)->exists());

        return $candidate;
    }
}
