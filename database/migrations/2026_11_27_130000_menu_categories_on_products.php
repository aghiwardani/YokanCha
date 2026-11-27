<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Products now belong to a menu tab: "matcha" or "coffee". Everything sold so
 * far is matcha (it was stored as the old free-text "minuman").
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->whereNotIn('category', ['matcha', 'coffee'])->update(['category' => 'matcha']);

        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->default('matcha')->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->default('minuman')->change();
        });
    }
};
