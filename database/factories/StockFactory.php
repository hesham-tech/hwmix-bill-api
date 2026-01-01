<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Stock>
 */
class StockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quantity' => 100,
            'status' => 'available',
            'variant_id' => \App\Models\ProductVariant::factory(),
            'warehouse_id' => \App\Models\Warehouse::factory(),
            'company_id' => \App\Models\Company::factory(),
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
