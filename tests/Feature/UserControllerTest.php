<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\CompanyUser;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
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

        // Link admin to company_user
        CompanyUser::create([
            'user_id' => $this->admin->id,
            'company_id' => $this->company->id,
            'nickname_in_company' => $this->admin->username,
            'status' => 'active',
        ]);
    }

    public function test_can_list_users()
    {
        $this->actingAs($this->admin);

        // Create another user in the same company
        $user = User::factory()->create(['company_id' => $this->company->id]);
        CompanyUser::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'nickname_in_company' => 'Test User',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200);
        $response->assertJsonFragment(['nickname' => 'Test User']);
    }

    public function test_can_create_user()
    {
        $this->actingAs($this->admin);

        $payload = [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'phone' => '123456789',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nickname' => 'New Nickname',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/user', $payload);

        $response->assertStatus(200); // Controller returns 200 for success
        $this->assertDatabaseHas('users', ['username' => 'newuser']);
        $this->assertDatabaseHas('company_user', ['nickname_in_company' => 'New Nickname']);
    }

    public function test_can_show_user()
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'nickname' => 'Target User'
        ]);
        CompanyUser::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'nickname_in_company' => 'Target User',
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/user/{$user->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['nickname' => 'Target User']);
    }

    public function test_can_update_user()
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create(['company_id' => $this->company->id]);
        CompanyUser::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'nickname_in_company' => 'Old Nickname',
            'status' => 'active',
        ]);

        $payload = [
            'nickname' => 'Updated Nickname',
        ];

        $response = $this->putJson("/api/user/{$user->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('company_user', ['nickname_in_company' => 'Updated Nickname']);
    }

    public function test_can_change_user_company()
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create(['company_id' => $this->company->id]);
        $newCompany = Company::factory()->create();

        // Links
        CompanyUser::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'nickname_in_company' => 'Company A',
        ]);
        CompanyUser::create([
            'user_id' => $user->id,
            'company_id' => $newCompany->id,
            'nickname_in_company' => 'Company B',
        ]);

        $payload = [
            'company_id' => $newCompany->id,
        ];

        $response = $this->putJson("/api/change-company/{$user->id}", $payload);

        $response->assertStatus(200);
        $this->assertEquals($newCompany->id, $user->fresh()->company_id);
    }

    public function test_can_batch_delete_users()
    {
        $this->actingAs($this->admin);

        $user1 = User::factory()->create(['company_id' => $this->company->id]);
        $user2 = User::factory()->create(['company_id' => $this->company->id]);

        CompanyUser::create(['user_id' => $user1->id, 'company_id' => $this->company->id, 'nickname_in_company' => 'User 1']);
        CompanyUser::create(['user_id' => $user2->id, 'company_id' => $this->company->id, 'nickname_in_company' => 'User 2']);

        $payload = [
            'item_ids' => [$user1->id, $user2->id]
        ];

        $response = $this->postJson('/api/users/delete', $payload);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $user1->id]);
        $this->assertDatabaseMissing('users', ['id' => $user2->id]);
    }

    public function test_user_cannot_view_users_from_another_company()
    {
        // Manager in company A
        $manager = User::factory()->create(['company_id' => $this->company->id]);
        $manager->givePermissionTo('admin.company');
        CompanyUser::create(['user_id' => $manager->id, 'company_id' => $this->company->id]);

        $this->actingAs($manager);

        // User in company B
        $companyB = Company::factory()->create();
        $userB = User::factory()->create(['company_id' => $companyB->id]);
        CompanyUser::create(['user_id' => $userB->id, 'company_id' => $companyB->id, 'nickname_in_company' => 'User B']);

        // Try to list (should only see users in company A)
        $response = $this->getJson('/api/users');
        $response->assertStatus(200);
        $response->assertJsonMissing(['nickname' => 'User B']);

        // Try to show User B directly
        $response = $this->getJson("/api/user/{$userB->id}");
        $response->assertStatus(404); // UserController returns 404 for user not in active company
    }
}
