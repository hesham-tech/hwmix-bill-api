<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\CashBox;
use App\Models\CashBoxType;
use App\Models\Transaction;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashBoxControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected CashBoxType $boxType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->givePermissionTo('admin.super');

        $this->boxType = CashBoxType::factory()->create();
    }

    public function test_can_list_cash_boxes()
    {
        $this->actingAs($this->admin);
        CashBox::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->getJson('/api/v1/cash-boxes');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data']);
    }

    public function test_can_create_cash_box()
    {
        $this->actingAs($this->admin);

        $payload = [
            'name' => 'Main Safe',
            'cash_box_type_id' => $this->boxType->id,
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'is_active' => false
        ];

        $response = $this->postJson('/api/v1/cash-boxes', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('cash_boxes', ['name' => 'Main Safe', 'company_id' => $this->company->id, 'is_active' => false]);
    }

    public function test_can_show_cash_box()
    {
        $this->actingAs($this->admin);
        $box = CashBox::factory()->create(['company_id' => $this->company->id]);

        $response = $this->getJson("/api/v1/cash-boxes/{$box->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $box->id);
    }

    public function test_can_update_cash_box()
    {
        $this->actingAs($this->admin);
        $box = CashBox::factory()->create(['company_id' => $this->company->id, 'name' => 'Old Name', 'is_active' => true]);

        $payload = ['name' => 'Updated Name', 'is_active' => false];

        $response = $this->putJson("/api/v1/cash-boxes/{$box->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('cash_boxes', ['id' => $box->id, 'name' => 'Updated Name', 'is_active' => false]);
    }

    public function test_can_delete_cash_box()
    {
        $this->actingAs($this->admin);
        $box = CashBox::factory()->create(['company_id' => $this->company->id]);

        $response = $this->deleteJson("/api/v1/cash-boxes/{$box->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('cash_boxes', ['id' => $box->id]);
    }

    public function test_cannot_delete_cash_box_with_transactions()
    {
        $this->actingAs($this->admin);
        $box = CashBox::factory()->create(['company_id' => $this->company->id]);

        Transaction::create([
            'user_id' => $this->admin->id,
            'cashbox_id' => $box->id,
            'company_id' => $this->company->id,
            'type' => 'test',
            'amount' => 100,
            'created_by' => $this->admin->id
        ]);

        $response = $this->deleteJson("/api/v1/cash-boxes/{$box->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('cash_boxes', ['id' => $box->id]);
    }

    public function test_can_transfer_funds_between_boxes()
    {
        $this->actingAs($this->admin);

        $sourceBox = CashBox::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'balance' => 1000
        ]);

        $targetUser = User::factory()->create(['company_id' => $this->company->id]);
        $targetBox = CashBox::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $targetUser->id,
            'balance' => 0
        ]);

        $payload = [
            'cash_box_id' => $sourceBox->id,
            'to_cash_box_id' => $targetBox->id,
            'to_user_id' => $targetUser->id,
            'amount' => 500,
            'description' => 'Test transfer'
        ];

        $response = $this->postJson('/api/v1/cash-boxes/transfer', $payload);

        $response->assertStatus(200);
        $this->assertEquals(500, $sourceBox->fresh()->balance);
        $this->assertEquals(500, $targetBox->fresh()->balance);
        $this->assertEquals(3, Transaction::count());

        $this->assertDatabaseHas('transactions', [
            'cashbox_id' => $sourceBox->id,
            'type' => 'transfer_out',
            'amount' => 500
        ]);
        $this->assertDatabaseHas('transactions', [
            'cashbox_id' => $targetBox->id,
            'type' => 'transfer_in',
            'amount' => 500
        ]);
    }

    public function test_cannot_transfer_funds_with_insufficient_balance()
    {
        $this->actingAs($this->admin);

        $sourceBox = CashBox::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'balance' => 100
        ]);

        $targetUser = User::factory()->create(['company_id' => $this->company->id]);
        $targetBox = CashBox::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $targetUser->id,
            'balance' => 0
        ]);

        $payload = [
            'cash_box_id' => $sourceBox->id,
            'to_cash_box_id' => $targetBox->id,
            'to_user_id' => $targetUser->id,
            'amount' => 500
        ];

        $response = $this->postJson('/api/v1/cash-boxes/transfer', $payload);

        $response->assertStatus(422);
        $this->assertEquals(100, $sourceBox->fresh()->balance);
    }

    public function test_data_isolation_cannot_access_other_company_cash_box()
    {
        $otherCompany = Company::factory()->create();
        $otherBox = CashBox::factory()->create(['company_id' => $otherCompany->id]);

        $companyAdmin = User::factory()->create(['company_id' => $this->company->id]);
        $companyAdmin->givePermissionTo('cash_boxes.view_all', 'cash_boxes.update_all', 'cash_boxes.delete_all');

        $this->actingAs($companyAdmin);

        $response = $this->getJson("/api/v1/cash-boxes/{$otherBox->id}");
        $response->assertStatus(404);

        $response = $this->putJson("/api/v1/cash-boxes/{$otherBox->id}", ['name' => 'Hack']);
        $response->assertStatus(404);

        $response = $this->deleteJson("/api/v1/cash-boxes/{$otherBox->id}");
        $response->assertStatus(404);
    }

    public function test_admin_cannot_see_other_boxes_by_default()
    {
        $this->actingAs($this->admin);

        // My box
        $myBox = CashBox::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id
        ]);

        // Other user's box
        $otherUser = User::factory()->create(['company_id' => $this->company->id]);
        $otherBox = CashBox::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $otherUser->id
        ]);

        $response = $this->getJson('/api/v1/cash-boxes');
        $response->assertStatus(200);

        $boxIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($boxIds->contains($myBox->id));
        $this->assertFalse($boxIds->contains($otherBox->id));
    }

    public function test_admin_can_see_all_boxes_with_parameter()
    {
        $this->actingAs($this->admin);

        // My box
        $myBox = CashBox::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id
        ]);

        // Other user's box
        $otherUser = User::factory()->create(['company_id' => $this->company->id]);
        $otherBox = CashBox::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $otherUser->id
        ]);

        $response = $this->getJson('/api/v1/cash-boxes?all_company_boxes=true');
        $response->assertStatus(200);

        $boxIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($boxIds->contains($myBox->id));
        $this->assertTrue($boxIds->contains($otherBox->id));
    }

    public function test_regular_user_cannot_see_all_boxes_even_with_parameter()
    {
        $branch = \Modules\Companies\Models\Branch::create([
            'name' => 'Branch 1',
            'company_id' => $this->company->id,
            'is_default' => true,
            'email' => 'branch1@test.com',
            'created_by' => $this->admin->id
        ]);

        $regularUser = User::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id
        ]);
        $this->actingAs($regularUser);

        // My box
        $myBox = CashBox::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'user_id' => $regularUser->id
        ]);

        // Other user's box
        $otherUser = User::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id
        ]);
        $otherBox = CashBox::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'user_id' => $otherUser->id
        ]);

        $response = $this->getJson('/api/v1/cash-boxes?all_company_boxes=true');
        $response->assertStatus(200);

        $boxIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($boxIds->contains($myBox->id));
        $this->assertFalse($boxIds->contains($otherBox->id));
    }

    public function test_can_create_multiple_default_boxes_across_branches()
    {
        $this->actingAs($this->admin);

        $branch1 = \Modules\Companies\Models\Branch::create([
            'name' => 'Branch 1',
            'company_id' => $this->company->id,
            'is_default' => true,
            'email' => 'b1@test.com',
            'created_by' => $this->admin->id
        ]);
        $branch2 = \Modules\Companies\Models\Branch::create([
            'name' => 'Branch 2',
            'company_id' => $this->company->id,
            'is_default' => false,
            'email' => 'b2@test.com',
            'created_by' => $this->admin->id
        ]);

        // Box 1 in Branch 1 as default
        $box1 = CashBox::create([
            'name' => 'Box Branch 1',
            'cash_box_type_id' => $this->boxType->id,
            'company_id' => $this->company->id,
            'branch_id' => $branch1->id,
            'user_id' => $this->admin->id,
            'is_default' => true,
            'created_by' => $this->admin->id
        ]);

        // Box 2 in Branch 2 as default (different branch) should succeed now
        $box2 = CashBox::create([
            'name' => 'Box Branch 2',
            'cash_box_type_id' => $this->boxType->id,
            'company_id' => $this->company->id,
            'branch_id' => $branch2->id,
            'user_id' => $this->admin->id,
            'is_default' => true,
            'created_by' => $this->admin->id
        ]);

        $this->assertTrue((bool)$box1->fresh()->is_default);
        $this->assertTrue((bool)$box2->fresh()->is_default);
    }
}
