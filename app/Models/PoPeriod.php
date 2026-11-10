<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PoPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'opens_at',
        'closes_at',
        'status',
    ];

    protected $casts = [
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'status' => 'string',
    ];

    public function poPeriodProducts(): HasMany
    {
        return $this->hasMany(PoPeriodProduct::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The single period (if any) that is currently accepting orders: status
     * is 'open' and the current time is within its opens_at/closes_at window.
     *
     * This is the one source of truth for "which period is open right now" —
     * both MenuController and CheckoutController must use this instead of
     * re-implementing the query.
     */
    public static function currentlyOpen(): ?self
    {
        $now = now();

        return static::where('status', 'open')
            ->where(function ($query) use ($now) {
                $query->whereNull('opens_at')->orWhere('opens_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('closes_at')->orWhere('closes_at', '>=', $now);
            })
            ->first();
    }

    public function isOpen(): bool
    {
        if ($this->status !== 'open') {
            return false;
        }

        $now = now();

        if ($this->opens_at && $now->lt($this->opens_at)) {
            return false;
        }

        if ($this->closes_at && $now->gt($this->closes_at)) {
            return false;
        }

        return true;
    }
}
