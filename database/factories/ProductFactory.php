<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'category' => 'minuman',
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'photo_path' => null,
            'base_price' => $this->faker->numberBetween(10000, 30000),
            'size_options' => ['250ml', '1L'],
            'sweetness_options' => ['Normal', 'Kurang Gula'],
        ];
    }
}
