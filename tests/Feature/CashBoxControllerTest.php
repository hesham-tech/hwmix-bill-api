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

        $response = $this->getJson('/api/cashBoxs'); // Note the 's' in cashBoxs as seen in api.php

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
            'user_id' => $this->admin->id
        ];

        $response = $this->postJson('/api/cashBox', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('cash_boxes', ['name' => 'Main Safe', 'company_id' => $this->company->id]);
    }

    public function test_can_show_cash_box()
    {
        $this->actingAs($this->admin);
        $box = CashBox::factory()->create(['company_id' => $this->company->id]);

        $response = $this->getJson("/api/cashBox/{$box->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $box->id);
    }

    public function test_can_update_cash_box()
    {
        $this->actingAs($this->admin);
        $box = CashBox::factory()->create(['company_id' => $this->company->id, 'name' => 'Old Name']);

        $payload = ['name' => 'Updated Name'];

        $response = $this->putJson("/api/cashBox/{$box->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('cash_boxes', ['id' => $box->id, 'name' => 'Updated Name']);
    }

    public function test_can_delete_cash_box()
    {
        $this->actingAs($this->admin);
        $box = CashBox::factory()->create(['company_id' => $this->company->id]);

        $response = $this->deleteJson("/api/cashBox/{$box->id}");

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

        $response = $this->deleteJson("/api/cashBox/{$box->id}");

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

        $response = $this->postJson('/api/cashBox/transfer', $payload);

        $response->assertStatus(200);
        $this->assertEquals(500, $sourceBox->fresh()->balance);
        $this->assertEquals(500, $targetBox->fresh()->balance);
        $this->assertEquals(2, Transaction::count());

        $this->assertDatabaseHas('transactions', [
            'cashbox_id' => $sourceBox->id,
            'amount' => -500
        ]);
        $this->assertDatabaseHas('transactions', [
            'cashbox_id' => $targetBox->id,
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

        $response = $this->postJson('/api/cashBox/transfer', $payload);

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

        $response = $this->getJson("/api/cashBox/{$otherBox->id}");
        $response->assertStatus(404);

        $response = $this->putJson("/api/cashBox/{$otherBox->id}", ['name' => 'Hack']);
        $response->assertStatus(404);

        $response = $this->deleteJson("/api/cashBox/{$otherBox->id}");
        $response->assertStatus(404);
    }
}
