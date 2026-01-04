<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Payment;
use App\Models\CashBox;
use App\Models\PaymentMethod;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected CashBox $cashBox;
    protected PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->givePermissionTo('admin.super');

        $this->cashBox = CashBox::factory()->create(['company_id' => $this->company->id]);
        $this->paymentMethod = PaymentMethod::factory()->create(['company_id' => $this->company->id]);
    }

    /** @test */
    public function test_can_list_payments()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin);

        Payment::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/payments');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'message']);
    }

    /** @test */
    public function test_can_create_payment()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin);

        $payload = [
            'user_id' => $this->admin->id,
            'payment_date' => now()->toDateString(),
            'amount' => 1500.75,
            'method' => 'cash',
            'notes' => 'Test payment notes',
            'is_split' => false,
            'payment_method_id' => $this->paymentMethod->id,
            'cash_box_id' => $this->cashBox->id, // Controller expects this in store method
        ];

        $response = $this->postJson('/api/payment', $payload);

        if ($response->status() !== 201) {
            dump($response->json());
        }

        $response->assertStatus(201);
        $this->assertDatabaseHas('payments', [
            'amount' => 1500.75,
            'notes' => 'Test payment notes'
        ]);
    }

    /** @test */
    public function test_can_show_payment()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin);

        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson("/api/payment/{$payment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $payment->id);
    }

    /** @test */
    public function test_can_update_payment()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin);

        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        $payload = [
            'amount' => 2000,
            'notes' => 'Updated notes'
        ];

        $response = $this->putJson("/api/payment/{$payment->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'amount' => 2000,
            'notes' => 'Updated notes'
        ]);
    }

    /** @test */
    public function test_can_delete_payment()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin);

        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->deleteJson("/api/payment/{$payment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    }
}
