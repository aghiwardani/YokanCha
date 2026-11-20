<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Usual purchase price of an ingredient: e.g. 1.000 ml of Oatside for
    // Rp38.000. Used to fill a period's purchase cost in one click.
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->decimal('pack_size', 12, 2)->nullable()->after('is_sugar');
            $table->unsignedBigInteger('pack_price')->nullable()->after('pack_size');
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn(['pack_size', 'pack_price']);
        });
    }
};
