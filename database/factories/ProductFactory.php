<?php

namespace Database\Factories;

use Modules\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Inventory\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word;
        return [
            'name' => $name,
            'slug' => Product::generateSlug($name),
            'active' => true,
            'featured' => false,
            'returnable' => true,
            'desc' => $this->faker->sentence,
            'category_id' => \Modules\Inventory\Models\Category::factory(),
            'brand_id' => \Modules\Inventory\Models\Brand::factory(),
            'company_id' => \App\Models\Company::factory(),
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
