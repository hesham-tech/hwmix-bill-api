<?php
namespace Tests\Feature;
use App\Models\User;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugStoreTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void {
        parent::setUp();
        $this->seed(AddPermissionsSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
    public function test_store() {
        $this->withoutExceptionHandling();
        $company = \App\Models\Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'active_company_id' => $company->id,
        ]);
        setPermissionsTeamId($company->id);
        $admin->givePermissionTo(perm_key('owner_fund_transactions.create'));
        
        $boxType = \App\Models\CashBoxType::factory()->create([
            'company_id' => $company->id,
            'name' => 'Cash',
        ]);

        $cashBox = \App\Models\CashBox::create([
            'company_id' => $company->id,
            'branch_id' => 1,
            'cash_box_type_id' => $boxType->id,
            'name' => 'Main Box',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => 1,
            'created_by' => $admin->id
        ]);

        $partner = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $payload = [
            'cashbox_id' => $cashBox->id,
            'user_id' => $partner->id,
            'type' => 'loan_to_owner',
            'amount' => 5000.00,
            'entry_date' => now()->toIso8601String(),
        ];
        
        $response = $this->actingAs($admin)->postJson('/api/v1/owner-fund-transactions', $payload);
        $response->dump();
    }
}
