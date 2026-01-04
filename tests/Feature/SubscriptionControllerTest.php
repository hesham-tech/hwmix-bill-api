<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\Plan;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->givePermissionTo('admin.super');

        $this->plan = Plan::factory()->create(['company_id' => $this->company->id]);
    }

    /** @test */
    public function test_can_list_subscriptions()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin);

        Subscription::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'plan_id' => $this->plan->id,
        ]);

        $response = $this->getJson('/api/subscriptions');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'message']);
    }

    /** @test */
    public function test_can_create_subscription()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin);

        $subscriber = User::factory()->create(['company_id' => $this->company->id]);

        $payload = [
            'user_id' => $subscriber->id,
            'plan_id' => $this->plan->id,
            'starts_at' => now()->toDateTimeString(),
            'ends_at' => now()->addYear()->toDateTimeString(),
            'billing_cycle' => 'yearly',
            'price' => 1200,
            'status' => 'active',
            'notes' => 'Test subscription',
            'company_id' => $this->company->id,
        ];

        $response = $this->postJson('/api/subscription', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $subscriber->id,
            'plan_id' => $this->plan->id,
            'price' => 1200
        ]);
    }

    /** @test */
    public function test_can_show_subscription()
    {
        $this->actingAs($this->admin);

        $subscription = Subscription::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'plan_id' => $this->plan->id,
        ]);

        $response = $this->getJson("/api/subscription/{$subscription->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $subscription->id);
    }

    /** @test */
    public function test_can_update_subscription()
    {
        $this->actingAs($this->admin);

        $subscription = Subscription::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'plan_id' => $this->plan->id,
        ]);

        $payload = [
            'price' => 1500,
            'notes' => 'Updated notes'
        ];

        $response = $this->putJson("/api/subscription/{$subscription->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'price' => 1500,
            'notes' => 'Updated notes'
        ]);
    }

    /** @test */
    public function test_can_delete_subscription()
    {
        $this->actingAs($this->admin);

        $subscription = Subscription::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'plan_id' => $this->plan->id,
        ]);

        $response = $this->deleteJson("/api/subscription/delete/{$subscription->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
    }
}
