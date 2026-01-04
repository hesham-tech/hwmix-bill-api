<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\User;
use App\Models\Company;
use App\Models\CashBox;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'created_by' => User::factory(),
            'payment_date' => $this->faker->date(),
            'amount' => $this->faker->randomFloat(2, 10, 5000),
            'method' => $this->faker->randomElement(['cash', 'bank_transfer', 'visa']),
            'notes' => $this->faker->sentence,
            'is_split' => false,
            'cash_box_id' => CashBox::factory(),
            'payment_method_id' => PaymentMethod::factory(),
        ];
    }
}
