<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->admin->givePermissionTo('admin.super');
    }

    public function test_can_list_companies()
    {
        $this->actingAs($this->admin);

        Company::factory()->count(3)->create();
        // dump(Company::pluck('name')->toArray());

        $response = $this->getJson('/api/v1/companies'); // Note the route is 'companies' in api.php

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data'); // 3 new + 1 from setUp + 1 from seeder (System Company)
    }

    public function test_can_create_company()
    {
        $this->actingAs($this->admin);

        $payload = [
            'name' => 'New Test Company',
            'email' => 'contact@testcompany.com',
            'phone' => '987654321',
            'address' => 'Test Address',
        ];

        $response = $this->postJson('/api/v1/companies', $payload);

        $response->assertStatus(201); // 201 Created is returned by store()
        $this->assertDatabaseHas('companies', ['name' => 'New Test Company']);

        // Check if default warehouse was created
        $newCompany = Company::where('name', 'New Test Company')->first();
        $this->assertDatabaseHas('warehouses', [
            'name' => 'المخزن الرئيسي',
            'company_id' => $newCompany->id
        ]);

        // Check if admin user's active company was updated
        $this->assertEquals($newCompany->id, $this->admin->fresh()->company_id);
    }

    public function test_can_show_company()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson("/api/v1/companies/{$this->company->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $this->company->name]);
    }

    public function test_can_update_company()
    {
        $this->actingAs($this->admin);

        $payload = [
            'name' => 'Updated Company Name',
        ];

        $response = $this->putJson("/api/v1/companies/{$this->company->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('companies', ['name' => 'Updated Company Name']);
    }

    public function test_can_batch_delete_companies()
    {
        $this->actingAs($this->admin);

        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        $payload = [
            'item_ids' => [$company1->id, $company2->id]
        ];

        $response = $this->postJson('/api/v1/companies/delete', $payload);

        $response->assertStatus(200);
        $this->assertSoftDeleted('companies', ['id' => $company1->id]);
        $this->assertSoftDeleted('companies', ['id' => $company2->id]);
    }

    public function test_can_view_companies_trash()
    {
        $this->actingAs($this->admin);

        $company1 = Company::factory()->create();
        $company1->delete();

        $response = $this->getJson('/api/v1/companies/trash');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $company1->name]);
    }

    public function test_can_restore_companies_from_trash()
    {
        $this->actingAs($this->admin);

        $company1 = Company::factory()->create();
        $company1->delete();

        $this->assertSoftDeleted('companies', ['id' => $company1->id]);

        $payload = [
            'item_ids' => [$company1->id]
        ];

        $response = $this->postJson('/api/v1/companies/restore', $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('companies', [
            'id' => $company1->id,
            'deleted_at' => null
        ]);
    }

    public function test_can_force_delete_company_and_cleanup_users()
    {
        $this->actingAs($this->admin);

        $company1 = Company::factory()->create();
        
        // Create user who belongs only to company1
        $userOnlyCompany1 = User::factory()->create(['company_id' => $company1->id]);
        $company1->users()->attach($userOnlyCompany1->id, ['created_by' => $this->admin->id]);

        // Create user who belongs to company1 AND another company
        $otherCompany = Company::factory()->create();
        $userMultipleCompanies = User::factory()->create([
            'company_id' => $otherCompany->id,
            'active_company_id' => $company1->id
        ]);
        $company1->users()->attach($userMultipleCompanies->id, ['created_by' => $this->admin->id]);
        $otherCompany->users()->attach($userMultipleCompanies->id, ['created_by' => $this->admin->id]);

        // Soft delete first
        $company1->delete();

        // Force delete
        $payload = [
            'item_ids'   => [$company1->id]
        ];

        $response = $this->postJson('/api/v1/companies/force-delete', $payload);

        $response->assertStatus(200);

        // Company is deleted permanently
        $this->assertDatabaseMissing('companies', ['id' => $company1->id]);

        // User who only belonged to company1 should be deleted permanently
        $this->assertDatabaseMissing('users', ['id' => $userOnlyCompany1->id]);

        // User who belongs to multiple companies should NOT be deleted, and active_company_id should remain pointing to company1 (which is deleted)
        $this->assertDatabaseHas('users', [
            'id' => $userMultipleCompanies->id,
            'active_company_id' => $company1->id
        ]);
    }

    public function test_regular_user_cannot_view_all_companies()
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->givePermissionTo('companies.view_self');

        $this->actingAs($user);

        $otherCompany = Company::factory()->create();

        $response = $this->getJson('/api/v1/companies');

        $response->assertStatus(200);
        // Should only see companies they belong to (just $this->company)
        $response->assertJsonMissing(['name' => $otherCompany->name]);
    }

    public function test_can_get_public_company()
    {
        $firstCompany = Company::withoutGlobalScopes()->first();

        // No authentication required
        $response = $this->getJson('/api/v1/public/company');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $firstCompany->name]);
    }
}
