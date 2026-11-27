<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra matcha also needs more water to dissolve it, and less milk so the
 * bottle holds the same amount: mark which ingredients are water and milk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->boolean('is_water')->default(false)->after('is_matcha');
            $table->boolean('is_milk')->default(false)->after('is_water');
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', fn (Blueprint $table) => $table->dropColumn(['is_water', 'is_milk']));
    }
};
