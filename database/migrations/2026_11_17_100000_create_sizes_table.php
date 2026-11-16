<?php

use App\Models\Size;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sizes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('volume_ml');
            $table->timestamps();
        });

        // On an existing install, seed the master list from the sizes products
        // already use (so nothing disappears from the product form), then add
        // the usual sizes (250 ml, 500 ml, 1 liter) when no size of that volume
        // exists yet. A fresh database (no products) starts empty.
        if (! DB::table('products')->exists()) {
            return;
        }

        $now = now();
        $volumes = [];

        foreach (DB::table('products')->pluck('size_options') as $json) {
            foreach ((array) json_decode((string) $json, true) as $name) {
                $name = trim((string) $name);

                if ($name === '' || isset($volumes[$name])) {
                    continue;
                }

                $volumes[$name] = Size::volumeFromName($name);
            }
        }

        foreach (['250 ml' => 250, '500 ml' => 500, '1 liter' => 1000] as $name => $volume) {
            if (! isset($volumes[$name]) && ! in_array($volume, $volumes, true)) {
                $volumes[$name] = $volume;
            }
        }

        foreach ($volumes as $name => $volume) {
            DB::table('sizes')->insert([
                'name' => $name,
                'volume_ml' => $volume,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sizes');
    }
};
