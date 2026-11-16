<?php

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
        $volumes = [];   // name => volume
        $seen = [];      // lower-cased name, so "250ML" doesn't collide with "250ml" on MySQL's case-insensitive unique index

        foreach (DB::table('products')->pluck('size_options') as $json) {
            foreach ((array) json_decode((string) $json, true) as $name) {
                $name = trim((string) $name);
                $key = mb_strtolower($name);

                if ($name === '' || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $volumes[$name] = $this->volumeFromName($name);
            }
        }

        foreach (['250 ml' => 250, '500 ml' => 500, '1 liter' => 1000] as $name => $volume) {
            if (! isset($seen[mb_strtolower($name)]) && ! in_array($volume, $volumes, true)) {
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

    // Copy of Size::volumeFromName() at the time of writing, so later model
    // changes can't alter what this migration does.
    private function volumeFromName(string $name): int
    {
        if (! preg_match('/(\d{1,3}(?:\.\d{3})+|\d+(?:[.,]\d+)?)\s*([a-z]*)/i', $name, $m)) {
            return 0;
        }

        $number = preg_match('/^\d{1,3}(?:\.\d{3})+$/', $m[1])
            ? (float) str_replace('.', '', $m[1])
            : (float) str_replace(',', '.', $m[1]);
        $unit = strtolower($m[2]);

        return (int) round($unit !== '' && $unit[0] === 'l' ? $number * 1000 : $number);
    }

    public function down(): void
    {
        Schema::dropIfExists('sizes');
    }
};
