<?php

namespace App\Services;

use App\Models\PoPeriodProduct;
use Illuminate\Support\Facades\DB;

class QuotaService
{
    /**
     * Atomically reserve $qty units of stock against the given PoPeriodProduct.
     *
     * Uses a DB transaction with a row-level lock (lockForUpdate) to prevent
     * concurrent requests from over-reserving the same quota. Either the full
     * quantity is reserved, or nothing changes at all.
     */
    public function reserve(PoPeriodProduct $ppp, int $qty): bool
    {
        return DB::transaction(function () use ($ppp, $qty) {
            $locked = PoPeriodProduct::query()
                ->lockForUpdate()
                ->findOrFail($ppp->getKey());

            if ($locked->remaining() < $qty) {
                return false;
            }

            $locked->increment('ordered_qty', $qty);

            $ppp->ordered_qty = $locked->ordered_qty;

            return true;
        });
    }

    /**
     * Release $qty units previously reserved, decrementing ordered_qty but
     * never letting it go below zero.
     */
    public function release(PoPeriodProduct $ppp, int $qty): void
    {
        DB::transaction(function () use ($ppp, $qty) {
            $locked = PoPeriodProduct::query()
                ->lockForUpdate()
                ->findOrFail($ppp->getKey());

            $newQty = max(0, $locked->ordered_qty - $qty);

            $locked->update(['ordered_qty' => $newQty]);

            $ppp->ordered_qty = $newQty;
        });
    }
}
