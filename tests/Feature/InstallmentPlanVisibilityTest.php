<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\InstallmentPlan;
use App\Models\Invoice;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class InstallmentPlanVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;
    protected Company $companyA;
    protected Company $companyB;
    protected User $crossCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);

        $this->companyA = Company::factory()->create();
        $this->companyB = Company::factory()->create();

        $this->staff = User::factory()->create(['company_id' => $this->companyA->id]);
        $this->staff->givePermissionTo(perm_key('installment_plans.view_all'));

        // Customer primarily in Company B
        $this->crossCustomer = User::factory()->create([
            'company_id' => $this->companyB->id,
            'full_name' => 'Wael Mohamed Ahmed',
            'nickname' => 'Abu Nadi'
        ]);

        // Associate customer with Company A manually
        DB::table('company_user')->insert([
            'company_id' => $this->companyA->id,
            'user_id' => $this->crossCustomer->id,
            'role' => 'customer',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /** @test */
    public function test_staff_can_view_plan_for_cross_company_customer()
    {
        // Create plan in Company A for a customer from Company B
        $plan = InstallmentPlan::factory()->create([
            'company_id' => $this->companyA->id,
            'user_id' => $this->crossCustomer->id,
            'created_by' => $this->staff->id
        ]);

        $this->actingAs($this->staff);

        // 1. Check index response
        $response = $this->getJson('/api/installment-plans');
        $response->assertStatus(200);

        $planIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($planIds->contains($plan->id), "Staff cannot see plan ID {$plan->id} in index");

        // 2. Check search logic
        $searchResponse = $this->getJson('/api/installment-plans?search=Nadi');
        $searchResponse->assertStatus(200);

        $searchPlanIds = collect($searchResponse->json('data'))->pluck('id');
        $this->assertTrue($searchPlanIds->contains($plan->id), "Staff cannot find plan ID {$plan->id} by searching customer nickname");
    }

    /** @test */
    public function test_staff_cannot_see_plans_from_other_companies()
    {
        // Create plan in Company B
        $planOther = InstallmentPlan::factory()->create([
            'company_id' => $this->companyB->id,
            'user_id' => $this->crossCustomer->id
        ]);

        $this->actingAs($this->staff);

        $response = $this->getJson('/api/installment-plans');
        $response->assertStatus(200);

        $planIds = collect($response->json('data'))->pluck('id');
        $this->assertFalse($planIds->contains($planOther->id), "Staff can see plan from another company");
    }
}
