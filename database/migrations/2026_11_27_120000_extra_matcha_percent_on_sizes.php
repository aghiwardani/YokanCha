<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra matcha is now a percent of the recipe's matcha (e.g. 50 = half as
 * much again) instead of a fixed number of grams per size.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sizes', function (Blueprint $table) {
            $table->unsignedSmallInteger('extra_matcha_percent')->default(0)->after('extra_matcha_price');
        });

        Schema::table('sizes', function (Blueprint $table) {
            $table->dropColumn('extra_matcha_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sizes', function (Blueprint $table) {
            $table->decimal('extra_matcha_amount', 10, 2)->default(0)->after('extra_matcha_price');
        });

        Schema::table('sizes', function (Blueprint $table) {
            $table->dropColumn('extra_matcha_percent');
        });
    }
};
