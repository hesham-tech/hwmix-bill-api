<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Inventory\Models\Stock>
 */
class StockFactory extends Factory
{
    protected $model = \Modules\Inventory\Models\Stock::class;

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
            'variant_id' => \Modules\Inventory\Models\ProductVariant::factory(),
            'warehouse_id' => \Modules\Inventory\Models\Warehouse::factory(),
            'company_id' => \App\Models\Company::factory(),
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
