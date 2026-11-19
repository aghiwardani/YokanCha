<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // What was paid in total (Rupiah) for this period's stock of the ingredient.
    public function up(): void
    {
        Schema::table('po_period_ingredients', function (Blueprint $table) {
            $table->unsignedBigInteger('cost')->nullable()->after('used');
        });
    }

    public function down(): void
    {
        Schema::table('po_period_ingredients', function (Blueprint $table) {
            $table->dropColumn('cost');
        });
    }
};
