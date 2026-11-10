<?php

namespace Database\Factories;

use App\Models\PoPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PoPeriodFactory extends Factory
{
    protected $model = PoPeriod::class;

    public function definition(): array
    {
        return [
            'name' => 'PO '.$this->faker->monthName().' '.$this->faker->year(),
            'opens_at' => now(),
            'closes_at' => now()->addDays(5),
            'status' => 'draft',
        ];
    }
}
