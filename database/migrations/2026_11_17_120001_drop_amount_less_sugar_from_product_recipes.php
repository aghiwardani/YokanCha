<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Sugar for other sweetness levels is now the recipe's Normal amount
    // times the level's percent (sweetness_levels), not a typed amount.
    public function up(): void
    {
        Schema::table('product_recipes', function (Blueprint $table) {
            $table->dropColumn('amount_less_sugar');
        });
    }

    public function down(): void
    {
        Schema::table('product_recipes', function (Blueprint $table) {
            $table->decimal('amount_less_sugar', 10, 2)->nullable()->after('amount');
        });
    }
};
