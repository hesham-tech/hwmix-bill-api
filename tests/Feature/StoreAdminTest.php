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

    public function test_seller_tax_configuration_requires_a_basis_and_scopes_product_exceptions(): void {
        $this->actingAs($this->admin,'sanctum');$seller=Company::factory()->create();
        $data=['is_enabled'=>false,'warehouse_id'=>null,'shipping_account_id'=>null,'policy_overrides'=>[],
            'pickup_address'=>['recipient_name'=>'Fixture','phone'=>'01012345678','street'=>'Fixture street','city'=>'Cairo','governorate_code'=>'cairo'],
            'tax_profile'=>['status'=>'not_registered','mode'=>'none','rate_bps'=>0,'basis'=>'Owner declaration','registration_number'=>null,'product_rules'=>[]]];
        $url='/api/v1/admin/store/sellers/'.$seller->id;
        $this->putJson($url,$data)->assertOk()->assertJsonPath('data.profile.tax_profile.status','not_registered');
        $data['tax_profile']['rate_bps']=1400;
        $this->putJson($url,$data)->assertStatus(422);
        $data['tax_profile']=['status'=>'registered','mode'=>'included','rate_bps'=>1400,'basis'=>'Fixture registration','registration_number'=>'VAT-FIXTURE','product_rules'=>[]];
        $this->putJson($url,$data)->assertOk();
        $foreign=\Modules\Inventory\Models\Product::factory()->create(['company_id'=>$this->admin->company_id]);
        $data['tax_profile']['product_rules']=[['product_id'=>$foreign->id,'treatment'=>'exempt','rate_bps'=>0,'basis'=>'Fixture']];
        $this->putJson($url,$data)->assertStatus(422)->assertJsonValidationErrors('tax_profile.product_rules.0.product_id');
        $data['tax_profile']['product_rules']=[];$data['tax_profile']['basis']='';
        $this->putJson($url,$data)->assertStatus(422)->assertJsonValidationErrors('tax_profile.basis');
        $this->assertSame('registered',StoreSellerProfile::where('company_id',$seller->id)->firstOrFail()->tax_profile['status']);
    }
    public function test_product_return_exception_requires_legal_basis_and_cannot_reduce_minimum_period(): void {
        $this->actingAs($this->admin,'sanctum');$seller=Company::factory()->create();
        $product=\Modules\Inventory\Models\Product::factory()->create(['company_id'=>$seller->id]);
        $url='/api/v1/admin/store/sellers/'.$seller->id.'/products/'.$product->id.'/return-policy';
        $data=['change_of_mind_days'=>14,'defect_days'=>30,'change_of_mind_exception'=>'custom_made','exception_basis'=>'Made to customer specification'];
        $this->assertDatabaseHas('products',['id'=>$product->id,'company_id'=>$seller->id]);
        $this->withoutExceptionHandling()->putJson($url,$data)->assertOk();
        $this->withExceptionHandling();
        $this->assertTrue($product->fresh()->store_return_policy['allow_defect']);
        $this->assertFalse($product->fresh()->store_return_policy['allow_change_of_mind']);
        $data['defect_days']=14;$this->putJson($url,$data)->assertStatus(422);
        $data['defect_days']=30;$data['exception_basis']='';$this->putJson($url,$data)->assertStatus(422);
        $this->putJson('/api/v1/admin/store/sellers/'.$this->admin->company_id.'/products/'.$product->id.'/return-policy',$data)->assertNotFound();
    }
}
