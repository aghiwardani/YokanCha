<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Costs besides recipe ingredients that go into HPP (COGS): packaging and
    // labour per bottle, or one-off costs per batch (ice, gas, transport).
    public function up(): void
    {
        Schema::create('po_period_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_period_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20); // per_bottle | per_batch
            $table->string('size')->nullable(); // per_bottle only; null = every size
            $table->unsignedBigInteger('amount');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_period_costs');
    }
};
