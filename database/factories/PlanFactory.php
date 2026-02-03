<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'code' => $this->faker->unique()->slug(2),
            'description' => $this->faker->sentence,
            'company_id' => Company::factory(),
            'price' => $this->faker->randomFloat(2, 50, 2000),
            'currency' => 'EGP',
            'duration' => $this->faker->numberBetween(1, 12),
            'duration_unit' => $this->faker->randomElement(['month', 'year']),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
