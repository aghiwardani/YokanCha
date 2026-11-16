<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_period_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_period_id')->constrained('po_periods')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->decimal('stock', 10, 2);
            $table->decimal('used', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['po_period_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_period_ingredients');
    }
};
