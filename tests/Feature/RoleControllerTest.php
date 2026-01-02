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
        ]);
        $this->admin->givePermissionTo('admin.super');
    }

    public function test_can_list_roles()
    {
        $this->actingAs($this->admin);

        Role::firstOrCreate(['name' => 'Test Role', 'guard_name' => 'web']);

        $response = $this->getJson('/api/roles');

        $response->assertStatus(200);
    }

    public function test_can_create_role()
    {
        $this->actingAs($this->admin);

        $payload = [
            'name' => 'New Unique Role',
            'permissions' => ['users.view_all', 'invoices.create']
        ];

        $response = $this->postJson('/api/role', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('roles', ['name' => 'New Unique Role']);
    }

    public function test_can_update_role()
    {
        $this->actingAs($this->admin);
        $role = Role::firstOrCreate(['name' => 'Old Name', 'guard_name' => 'web']);
        $role->companies()->syncWithoutDetaching([$this->company->id => ['created_by' => $this->admin->id]]);

        $payload = [
            'name' => 'Updated Name',
        ];

        $response = $this->putJson("/api/role/{$role->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('roles', ['name' => 'Updated Name']);
    }

    public function test_can_assign_role_to_user()
    {
        $this->actingAs($this->admin);
        $role = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $role->companies()->syncWithoutDetaching([$this->company->id => ['created_by' => $this->admin->id]]);

        $user = User::factory()->create(['company_id' => $this->company->id]);

        $payload = [
            'user_id' => $user->id,
            'roles' => ['Manager']
        ];

        $response = $this->postJson('/api/role/assignRole', $payload);

        $response->assertStatus(200);
        $this->assertTrue($user->fresh()->hasRole('Manager'));
    }

    public function test_cannot_delete_role_assigned_to_user()
    {
        $this->actingAs($this->admin);
        $role = Role::firstOrCreate(['name' => 'InUseRole', 'guard_name' => 'web']);
        $role->companies()->syncWithoutDetaching([$this->company->id => ['created_by' => $this->admin->id]]);

        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->assignRole('InUseRole');

        $payload = [
            'item_ids' => [$role->id]
        ];

        $response = $this->postJson('/api/role/delete', $payload);

        $response->assertStatus(409); // Conflict
        $this->assertDatabaseHas('roles', ['name' => 'InUseRole']);
    }

    public function test_regular_user_cannot_create_role()
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($user);

        $payload = [
            'name' => 'Unauthorized Role',
        ];

        $response = $this->postJson('/api/role', $payload);

        $response->assertStatus(403);
    }

    public function test_data_isolation_cannot_view_role_from_another_company()
    {
        // Company A Manager
        $managerA = User::factory()->create(['company_id' => $this->company->id]);
        $managerA->givePermissionTo('admin.company');
        $this->actingAs($managerA);

        // Company B Role
        $companyB = Company::factory()->create();
        $roleB = Role::firstOrCreate(['name' => 'CompanyB Role', 'guard_name' => 'web']);
        $roleB->companies()->attach($companyB->id, ['created_by' => $this->admin->id]);

        $response = $this->getJson("/api/role/{$roleB->id}");

        $response->assertStatus(403); // Forbidden access to other company roles
    }
}
