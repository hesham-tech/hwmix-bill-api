<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Role;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleControllerTest extends TestCase
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
            'active_company_id' => $this->company->id,
        ]);
        setPermissionsTeamId($this->company->id);
        $this->admin->givePermissionTo('admin.super');
    }

    public function test_can_list_roles()
    {
        $this->actingAs($this->admin);

        Role::firstOrCreate([
            'name' => 'Test Role',
            'guard_name' => 'web',
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'label' => 'Test Role Label'
        ]);

        $response = $this->getJson('/api/v1/roles');

        $response->assertStatus(200);
    }

    public function test_can_create_role()
    {
        $this->actingAs($this->admin);

        $payload = [
            'name' => 'New Unique Role',
            'label' => 'New Unique Role Label',
            'permissions' => ['users.view_all', 'invoices.create']
        ];

        $response = $this->postJson('/api/v1/roles', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('roles', ['name' => 'New Unique Role']);
    }

    public function test_can_update_role()
    {
        $this->actingAs($this->admin);
        $role = Role::firstOrCreate([
            'name' => 'Old Name',
            'guard_name' => 'web',
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'label' => 'Old Name Label'
        ]);

        $payload = [
            'name' => 'Updated Name',
        ];

        $response = $this->putJson("/api/v1/roles/{$role->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('roles', ['name' => 'Updated Name']);
    }

    public function test_can_assign_role_to_user()
    {
        $this->actingAs($this->admin);
        $role = Role::firstOrCreate([
            'name' => 'Manager',
            'guard_name' => 'web',
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'label' => 'Manager Label'
        ]);

        $user = User::factory()->create(['company_id' => $this->company->id]);

        $payload = [
            'user_id' => $user->id,
            'roles' => ['Manager']
        ];

        $response = $this->postJson('/api/v1/roles/assign', $payload);

        $response->assertStatus(200);
        $this->assertTrue($user->fresh()->hasRole('Manager'));
    }

    public function test_cannot_delete_role_assigned_to_user()
    {
        $this->actingAs($this->admin);
        $role = Role::firstOrCreate([
            'name' => 'InUseRole',
            'guard_name' => 'web',
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'label' => 'InUseRole Label'
        ]);

        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->assignRole('InUseRole');

        $payload = [
            'item_ids' => [$role->id]
        ];

        $response = $this->postJson('/api/v1/roles/batch-delete', $payload);

        $response->assertStatus(409); // Conflict
        $this->assertDatabaseHas('roles', ['name' => 'InUseRole']);
    }

    public function test_regular_user_cannot_create_role()
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($user);

        $payload = [
            'name' => 'Unauthorized Role',
            'label' => 'Unauthorized Role Label'
        ];

        $response = $this->postJson('/api/v1/roles', $payload);

        $response->assertStatus(403);
    }

    public function test_data_isolation_cannot_view_role_from_another_company()
    {
        // Company A Manager
        $managerA = User::factory()->create([
            'company_id' => $this->company->id,
            'active_company_id' => $this->company->id,
        ]);
        setPermissionsTeamId($this->company->id);
        $managerA->givePermissionTo('admin.company');
        $this->actingAs($managerA);

        // Company B Role
        $companyB = Company::factory()->create();
        $roleB = Role::firstOrCreate([
            'name' => 'CompanyB Role',
            'guard_name' => 'web',
            'company_id' => $companyB->id,
            'created_by' => $this->admin->id,
            'label' => 'CompanyB Role Label'
        ]);

        $response = $this->getJson("/api/v1/roles/{$roleB->id}");

        $response->assertStatus(403); // Forbidden access to other company roles
    }
}
