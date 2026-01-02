<?php

namespace Database\Factories;

use App\Models\InstallmentPlan;
use App\Models\User;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InstallmentPlan>
 */
class InstallmentPlanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = InstallmentPlan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'number_of_installments' => 3,
            'total_amount' => 3000,
            'down_payment' => 0,
            'remaining_amount' => 3000,
            'installment_amount' => 1000,
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'status' => 'active',
            'company_id' => Company::factory(),
            'created_by' => User::factory(),
            'invoice_id' => Invoice::factory(),
            'user_id' => User::factory(),
        ];
    }
}
