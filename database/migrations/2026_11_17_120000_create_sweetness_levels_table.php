<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sweetness_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Share of the recipe's Normal sugar amount: 50 = half, 150 = one and a half.
            $table->unsignedSmallInteger('percent');
            $table->timestamps();
        });

        // On an existing install, seed the master list from the levels products
        // already offer, then add the usual four when no level of that percent
        // exists yet. A fresh database (no products) starts empty.
        if (! DB::table('products')->exists()) {
            return;
        }

        $now = now();
        $percents = [];  // name => percent
        $seen = [];      // lower-cased names (MySQL unique index ignores case)

        foreach (DB::table('products')->pluck('sweetness_options') as $json) {
            foreach ((array) json_decode((string) $json, true) as $name) {
                $name = trim((string) $name);
                $key = mb_strtolower($name);

                if ($name === '' || $name === '-' || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $percents[$name] = $this->percentFromName($name);
            }
        }

        foreach (['Tanpa gula' => 0, 'Less sugar' => 50, 'Manis normal' => 100, 'Extra manis' => 150] as $name => $percent) {
            if (! isset($seen[mb_strtolower($name)]) && ! in_array($percent, $percents, true)) {
                $percents[$name] = $percent;
            }
        }

        foreach ($percents as $name => $percent) {
            DB::table('sweetness_levels')->insert([
                'name' => $name,
                'percent' => $percent,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    // Copy of SweetnessLevel::percentFromName() at the time of writing.
    private function percentFromName(string $name): int
    {
        $text = mb_strtolower(trim($name));

        return match (true) {
            str_contains($text, 'tanpa') || str_contains($text, 'no sugar') || str_contains($text, 'zero') => 0,
            str_contains($text, 'less') || str_contains($text, 'kurang') || str_contains($text, 'sedikit') => 50,
            str_contains($text, 'extra') || str_contains($text, 'ekstra') || str_contains($text, 'lebih') => 150,
            default => 100,
        };
    }

    public function down(): void
    {
        Schema::dropIfExists('sweetness_levels');
    }
};
