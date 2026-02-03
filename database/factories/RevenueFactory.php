<?php

namespace Database\Factories;

use App\Models\Revenue;
use Illuminate\Database\Eloquent\Factories\Factory;

class RevenueFactory extends Factory
{
    protected $model = Revenue::class;

    public function definition(): array
    {
        return [
            'source_type' => $this->faker->randomElement(['sale_invoice', 'service_invoice', 'manual']),
            'source_id' => $this->faker->numberBetween(0, 100),
            'user_id' => \App\Models\User::factory(),
            'created_by' => \App\Models\User::factory(),
            'wallet_id' => \App\Models\CashBox::factory(),
            'company_id' => \App\Models\Company::factory(),
            'amount' => $this->faker->randomFloat(2, 100, 1000),
            'paid_amount' => $this->faker->randomFloat(2, 50, 1000),
            'remaining_amount' => 0,
            'payment_method' => $this->faker->randomElement(['cash', 'bank', 'vodafone_cash']),
            'note' => $this->faker->sentence,
            'revenue_date' => $this->faker->date(),
        ];
    }
}
