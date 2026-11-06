<?php
namespace Tests\Feature;
use App\Models\{Company,User};
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Store\Models\{StoreSellerProfile,StoreShippingAccount};
use Tests\TestCase;

class StoreAdminTest extends TestCase {
    use RefreshDatabase;
    private User $admin;
    protected function setUp(): void {
        parent::setUp();$this->seed(AddPermissionsSeeder::class);
        $company=Company::factory()->create();config(['app.master_company_id'=>$company->id]);
        setPermissionsTeamId($company->id);
        $this->admin=User::factory()->create(['company_id'=>$company->id,'active_company_id'=>$company->id]);
        $this->admin->givePermissionTo('admin.super');
    }
    public function test_all_administration_tabs_load_without_provider_credentials(): void {
        $this->actingAs($this->admin,'sanctum');
        $this->getJson('/api/v1/admin/store/bootstrap')->assertOk()->assertJsonPath('data.settings.enabled',false)->assertJsonPath('data.permissions.settings',true);
        foreach(['orders','returns','refunds','accounts','payouts','outbox','shipping-accounts','shipping-drivers'] as $path) $this->getJson('/api/v1/admin/store/'.$path)->assertOk();
    }
    public function test_ordinary_customer_and_merchant_admin_cannot_manage_platform(): void {
        $seller=Company::factory()->create();$user=User::factory()->create(['company_id'=>$seller->id,'active_company_id'=>$seller->id]);
        $this->actingAs($user,'sanctum')->getJson('/api/v1/admin/store/bootstrap')->assertForbidden();
        $user->givePermissionTo('admin.company');
        $this->getJson('/api/v1/admin/store/bootstrap')->assertForbidden();
    }
    public function test_order_operator_sees_only_its_authorized_sections(): void {
        $user=User::factory()->create(['company_id'=>$this->admin->company_id,'active_company_id'=>$this->admin->company_id]);
        $user->givePermissionTo('store.orders.manage');$this->actingAs($user,'sanctum');
        $this->getJson('/api/v1/admin/store/bootstrap')->assertOk()->assertJsonPath('data.permissions.orders',true)->assertJsonPath('data.permissions.finance',false);
        $this->getJson('/api/v1/admin/store/accounts')->assertForbidden();
        $this->getJson('/api/v1/admin/store/shipping-accounts')->assertForbidden();
    }
    public function test_viewing_seller_does_not_create_a_profile_and_shipping_secrets_are_not_returned(): void {
        $this->actingAs($this->admin,'sanctum');$company=Company::factory()->create();
        $this->getJson('/api/v1/admin/store/sellers/'.$company->id)->assertOk();
        $this->assertFalse(StoreSellerProfile::where('company_id',$company->id)->exists());
        StoreShippingAccount::create(['company_id'=>$company->id,'driver'=>'manual','name'=>'Test','credentials'=>['api_key'=>'never-return-this']]);
        $response=$this->getJson('/api/v1/admin/store/shipping-accounts')->assertOk();
        $this->assertStringNotContainsString('never-return-this',$response->getContent());
    }
}
