<?php
namespace Tests\Feature;
use App\Models\{Company,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Store\Models\{StoreSellerProfile,StorePayout};
use Modules\Store\Services\StoreLedgerService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
class StoreVendorAccessTest extends TestCase {
    use RefreshDatabase;
    private User $vendor; private Company $company;
    protected function setUp(): void {
        parent::setUp();$platform=Company::factory()->create();config(['app.master_company_id'=>$platform->id]);
        $this->company=Company::factory()->create();setPermissionsTeamId($this->company->id);
        foreach(['admin.super','admin.company','store.vendor.orders.view','store.vendor.finance.view','store.vendor.finance.manage'] as $p)Permission::findOrCreate($p,'web');
        $this->vendor=User::factory()->create(['company_id'=>$this->company->id,'active_company_id'=>$this->company->id]);
        $this->vendor->companies()->attach($this->company->id,['status'=>'active','full_name_in_company'=>'Vendor fixture']);
        $this->vendor->givePermissionTo('store.vendor.finance.view');
        $this->actingAs($this->vendor,'sanctum');
    }
    public function test_finance_read_does_not_authorize_withdrawal_and_request_is_idempotent(): void {
        $ledger=app(StoreLedgerService::class);$account=$ledger->account($this->company->id);
        $ledger->post($account,'balance_minor',10000,'vendor:sale','settlement','asset:bank');
        StoreSellerProfile::create(['company_id'=>$this->company->id,'payout_destination'=>['bank_name'=>'Fixture bank','account_holder'=>'Fixture','account_number'=>'123456']]);
        $this->getJson('/api/v1/vendor/store/account')->assertOk()->assertJsonPath('data.available_for_payout_minor',10000);
        $body=['amount'=>'30.00','operation_key'=>(string)\Illuminate\Support\Str::uuid(),'company_id'=>99999];
        $this->postJson('/api/v1/vendor/store/payouts',$body)->assertForbidden();
        $this->vendor->givePermissionTo('store.vendor.finance.manage');
        $first=$this->postJson('/api/v1/vendor/store/payouts',$body)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/vendor/store/payouts',$body)->assertCreated()->assertJsonPath('data.id',$first);
        $this->assertDatabaseCount('store_payouts',1);$this->assertSame(3000,$account->refresh()->payout_held_minor);
        $this->assertSame($account->id,StorePayout::firstOrFail()->account_id);
    }
    public function test_company_admin_permission_does_not_grant_access_to_an_unrelated_company(): void {
        $other=Company::factory()->create();$this->vendor->update(['active_company_id'=>$other->id]);setPermissionsTeamId($other->id);
        $this->vendor->givePermissionTo('admin.company');
        $this->getJson('/api/v1/vendor/store/account')->assertForbidden();
    }
    public function test_notifications_are_private_to_the_user_or_active_company(): void {
        $this->vendor->givePermissionTo('store.vendor.orders.view');
        $base=['type'=>'order.created','title'=>'Fixture','message'=>'Private notification','created_at'=>now(),'updated_at'=>now()];
        $own=DB::table('store_notifications')->insertGetId($base+['deduplication_key'=>'own','user_id'=>$this->vendor->id]);
        $other=DB::table('store_notifications')->insertGetId($base+['deduplication_key'=>'other','company_id'=>Company::factory()->create()->id]);
        DB::table('store_notifications')->insert($base+['deduplication_key'=>'company','company_id'=>$this->company->id]);
        $this->getJson('/api/v1/store/account/notifications?scope=admin')->assertOk()->assertJsonPath('data.unread_count',1)->assertJsonCount(1,'data.notifications.data');
        $this->getJson('/api/v1/vendor/store/notifications')->assertOk()->assertJsonPath('data.unread_count',1);
        $this->postJson('/api/v1/store/account/notifications/'.$other.'/read')->assertNotFound();
        $this->postJson('/api/v1/store/account/notifications/'.$own.'/read')->assertOk();
        $this->getJson('/api/v1/store/account/notifications')->assertJsonPath('data.unread_count',0);
    }
}
