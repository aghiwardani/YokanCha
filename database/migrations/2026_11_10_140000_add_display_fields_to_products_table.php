<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Price per size label, e.g. {"250 ml": 32000, "1 liter": 110000}.
            // Sizes missing from this map fall back to base_price.
            $table->json('size_prices')->nullable()->after('size_options');
            // Small label shown on the menu card, e.g. "Maple Signature".
            $table->string('tag')->nullable()->after('sweetness_options');
            // Card photo background tone: amber | sage | oat.
            $table->string('tone')->nullable()->after('tag');
            // Bottle illustration fill style: maple | klasik | oat | murni | strawberry.
            $table->string('bottle_style')->nullable()->after('tone');
            // Display order on the menu; the first product is shown as the wide featured card.
            $table->unsignedInteger('sort_order')->default(0)->after('bottle_style');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['size_prices', 'tag', 'tone', 'bottle_style', 'sort_order']);
        });
    }
};
