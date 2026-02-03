<?php

namespace Database\Factories;

use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InstallmentPaymentFactory extends Factory
{
    protected $model = InstallmentPayment::class;

    public function definition(): array
    {
        return [
            'installment_plan_id' => InstallmentPlan::factory(),
            'company_id' => Company::factory(),
            'created_by' => User::factory(),
            'payment_date' => $this->faker->date(),
            'amount_paid' => $this->faker->randomFloat(2, 100, 5000),
            'payment_method' => 'cash',
            'notes' => $this->faker->optional()->sentence,
        ];
    }
}
