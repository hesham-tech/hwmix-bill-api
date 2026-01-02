<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceType;
use App\Models\User;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_type_id' => InvoiceType::factory(),
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'invoice_number' => $this->faker->unique()->bothify('INV-####-????'),
            'due_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'gross_amount' => $this->faker->randomFloat(2, 100, 10000),
            'net_amount' => function (array $attributes) {
                return $attributes['gross_amount']; // Simplified
            },
            'paid_amount' => 0,
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }
}
