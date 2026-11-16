<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('po_period_products', function (Blueprint $table) {
            $table->dropColumn(['quota', 'ordered_qty']);
        });
    }

    public function down(): void
    {
        Schema::table('po_period_products', function (Blueprint $table) {
            $table->integer('quota')->default(0);
            $table->integer('ordered_qty')->default(0);
        });
    }
};
