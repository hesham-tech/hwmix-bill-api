<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'service_id' => Service::factory(),
            'plan_id' => Plan::factory(),
            'company_id' => Company::factory(),
            'created_by' => User::factory(),
            'start_date' => $this->faker->date(),
            'starts_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'next_billing_date' => $this->faker->date(),
            'ends_at' => $this->faker->dateTimeBetween('now', '+1 year'),
            'billing_cycle' => $this->faker->randomElement(['monthly', 'yearly']),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'status' => $this->faker->randomElement(['active', 'expired', 'pending']),
            'notes' => $this->faker->sentence,
        ];
    }
}
