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

        $response = $this->getJson('/api/companys'); // Note the route is 'companys' in api.php

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

        $response = $this->postJson('/api/company', $payload);

        $response->assertStatus(200);
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

        $response = $this->getJson("/api/company/{$this->company->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $this->company->name]);
    }

    public function test_can_update_company()
    {
        $this->actingAs($this->admin);

        $payload = [
            'name' => 'Updated Company Name',
        ];

        $response = $this->putJson("/api/company/{$this->company->id}", $payload);

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

        $response = $this->postJson('/api/company/delete', $payload);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('companies', ['id' => $company1->id]);
        $this->assertDatabaseMissing('companies', ['id' => $company2->id]);
    }

    public function test_regular_user_cannot_view_all_companies()
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->givePermissionTo('companies.view_self');

        $this->actingAs($user);

        $otherCompany = Company::factory()->create();

        $response = $this->getJson('/api/companys');

        $response->assertStatus(200);
        // Should only see companies they belong to (just $this->company)
        $response->assertJsonMissing(['name' => $otherCompany->name]);
    }
}
