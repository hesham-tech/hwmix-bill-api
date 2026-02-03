<?php

namespace Database\Factories;

use App\Models\InstallmentPaymentDetail;
use App\Models\InstallmentPayment;
use App\Models\Installment;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InstallmentPaymentDetailFactory extends Factory
{
    protected $model = InstallmentPaymentDetail::class;

    public function definition(): array
    {
        return [
            'installment_payment_id' => InstallmentPayment::factory(),
            'installment_id' => Installment::factory(),
            'amount_paid' => $this->faker->randomFloat(2, 100, 5000),
        ];
    }
}
