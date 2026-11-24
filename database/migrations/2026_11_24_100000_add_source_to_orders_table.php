<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Where an order came from: "web" (the buyer checked out) or "admin"
    // (entered by hand, e.g. after the PO closed).
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('source', 10)->default('web')->after('order_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
