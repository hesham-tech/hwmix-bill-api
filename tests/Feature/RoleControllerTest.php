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
}
