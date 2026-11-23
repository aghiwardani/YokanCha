<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Part of a period's stock that is the previous period's leftover, and
    // what that part was worth. stock = carried + newly bought; cost = carried_cost
    // + what the new purchase cost.
    public function up(): void
    {
        Schema::table('po_period_ingredients', function (Blueprint $table) {
            $table->decimal('carried', 12, 2)->default(0)->after('used');
            $table->unsignedBigInteger('carried_cost')->default(0)->after('cost');
        });
    }

    public function down(): void
    {
        Schema::table('po_period_ingredients', function (Blueprint $table) {
            $table->dropColumn(['carried', 'carried_cost']);
        });
    }
};
