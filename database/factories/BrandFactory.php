<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Inventory\Models\Brand>
 */
class BrandFactory extends Factory
{
    protected $model = \Modules\Inventory\Models\Brand::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company;
        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'description' => $this->faker->sentence,
            'company_id' => \App\Models\Company::factory(),
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
