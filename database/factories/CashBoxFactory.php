<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashBox>
 */
class CashBoxFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word . ' Box',
            'balance' => 0,
            'is_default' => true,
            'cash_box_type_id' => \App\Models\CashBoxType::factory(),
            'company_id' => \App\Models\Company::factory(),
            'user_id' => \App\Models\User::factory(),
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
