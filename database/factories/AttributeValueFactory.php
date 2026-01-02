<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttributeValue>
 */
class AttributeValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attribute_id' => \App\Models\Attribute::factory(),
            'name' => $this->faker->word(),
            'color' => $this->faker->hexColor(),
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
