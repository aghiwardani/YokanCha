<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Extra matcha": an add-on priced per bottle size. The extra grams come out
 * of the ingredient marked as matcha; order items remember whether they had it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sizes', function (Blueprint $table) {
            $table->unsignedInteger('extra_matcha_price')->default(0)->after('volume_ml');
            $table->decimal('extra_matcha_amount', 10, 2)->default(0)->after('extra_matcha_price');
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->boolean('is_matcha')->default(false)->after('is_sugar');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('extra_matcha')->default(false)->after('sweetness');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('extra_matcha'));
        Schema::table('ingredients', fn (Blueprint $table) => $table->dropColumn('is_matcha'));
        Schema::table('sizes', fn (Blueprint $table) => $table->dropColumn(['extra_matcha_price', 'extra_matcha_amount']));
    }
};
