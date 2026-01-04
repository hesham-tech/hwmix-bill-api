<?php

namespace Database\Factories;

use App\Models\Profit;
use App\Models\User;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfitFactory extends Factory
{
    protected $model = Profit::class;

    public function definition(): array
    {
        $revenueAmount = $this->faker->randomFloat(2, 100, 1000);
        $costAmount = $this->faker->randomFloat(2, 50, $revenueAmount);
        $profitAmount = $revenueAmount - $costAmount;

        return [
            'source_type' => $this->faker->randomElement(['sale_invoice', 'service_invoice', 'manual']),
            'source_id' => $this->faker->numberBetween(0, 100),
            'created_by' => User::factory(),
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'revenue_amount' => $revenueAmount,
            'cost_amount' => $costAmount,
            'profit_amount' => $profitAmount,
            'note' => $this->faker->sentence,
            'profit_date' => $this->faker->date(),
        ];
    }
}
