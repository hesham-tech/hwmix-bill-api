<?php

namespace Database\Factories;

use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\User;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Installment>
 */
class InstallmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Installment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'installment_plan_id' => InstallmentPlan::factory(),
            'installment_number' => $this->faker->numberBetween(1, 10),
            'due_date' => $this->faker->dateTimeBetween('now', '+1 year'),
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'status' => 'pending', // Or equivalent enum value
            'remaining' => function (array $attributes) {
                return $attributes['amount'];
            },
            'created_by' => User::factory(),
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'invoice_id' => Invoice::factory(), // Assuming it relates to Invoice as implied by test but not in fillable? Wait, test uses invoice_id.
        ];
    }
}
