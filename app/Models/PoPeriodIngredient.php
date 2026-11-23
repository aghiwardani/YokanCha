<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoPeriodIngredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_period_id',
        'ingredient_id',
        'stock',
        'used',
        'cost',
        'carried',
        'carried_cost',
    ];

    protected $casts = [
        'stock' => 'decimal:2',
        'used' => 'decimal:2',
        'cost' => 'integer',
        'carried' => 'decimal:2',
        'carried_cost' => 'integer',
    ];

    public function poPeriod(): BelongsTo
    {
        return $this->belongsTo(PoPeriod::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function remaining(): float
    {
        return (float) $this->stock - (float) $this->used;
    }

    /**
     * Rupiah per unit (per ml, per g, ...), or null when no price was entered.
     */
    public function unitCost(): ?float
    {
        if ($this->cost === null || (float) $this->stock <= 0) {
            return null;
        }

        return $this->cost / (float) $this->stock;
    }

    /**
     * Newly bought this period (stock minus the leftover carried in).
     */
    public function bought(): float
    {
        return max(0, (float) $this->stock - (float) $this->carried);
    }

    /**
     * What the new purchase cost (money actually spent this period), or null
     * when no price was entered.
     */
    public function boughtCost(): ?int
    {
        return $this->cost === null ? null : max(0, $this->cost - (int) $this->carried_cost);
    }
}
