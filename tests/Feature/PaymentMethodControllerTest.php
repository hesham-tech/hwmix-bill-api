<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\PaymentMethod;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create(['id' => 1]);
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->givePermissionTo('admin.super');
    }

    public function test_can_list_payment_methods()
    {
        $this->actingAs($this->admin);
        PaymentMethod::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->getJson('/api/paymentMethods');
        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_can_create_payment_method()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/paymentMethod', [
            'name' => 'Credit Card',
            'description' => 'Visa/MasterCard payments'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('payment_methods', ['name' => 'Credit Card']);
    }

    public function test_can_show_payment_method()
    {
        $this->actingAs($this->admin);
        $method = PaymentMethod::factory()->create(['company_id' => $this->company->id]);

        $response = $this->getJson("/api/paymentMethod/{$method->id}");
        $response->assertStatus(200)->assertJsonPath('data.id', $method->id);
    }

    public function test_can_update_payment_method()
    {
        $this->actingAs($this->admin);
        $method = PaymentMethod::factory()->create(['company_id' => $this->company->id]);

        $response = $this->putJson("/api/paymentMethod/{$method->id}", [
            'name' => 'Updated Payment Method'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payment_methods', ['id' => $method->id, 'name' => 'Updated Payment Method']);
    }

    public function test_can_delete_payment_method()
    {
        $this->actingAs($this->admin);
        $method = PaymentMethod::factory()->create(['company_id' => $this->company->id]);

        $response = $this->deleteJson("/api/paymentMethod/{$method->id}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('payment_methods', ['id' => $method->id]);
    }
}
