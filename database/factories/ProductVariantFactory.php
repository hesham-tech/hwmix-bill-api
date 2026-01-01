<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'retail_price' => $this->faker->randomFloat(2, 10, 1000),
            'wholesale_price' => $this->faker->randomFloat(2, 5, 800),
            'min_quantity' => $this->faker->numberBetween(1, 10),
            'status' => 'active',
            'product_id' => \App\Models\Product::factory(),
            'company_id' => \App\Models\Company::factory(),
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
