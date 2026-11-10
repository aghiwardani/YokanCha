<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_period_id')->constrained('po_periods')->cascadeOnDelete();
            $table->string('customer_name');
            $table->string('customer_whatsapp');
            $table->string('payment_status')->default('unpaid');
            $table->string('order_status')->default('new');
            $table->string('order_number')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
