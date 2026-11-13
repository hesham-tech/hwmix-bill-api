<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Company,User};
use Modules\Store\Models\StoreSetting;
use Spatie\Permission\Models\Permission;
class StorePublicContactTest extends TestCase {
    use RefreshDatabase;
    private Company $platform;private Company $seller;
    private array $changed=['email'=>'contact@example.test','phone'=>'+20 100 123 4567','address'=>'القاهرة، مصر'];
    protected function setUp(): void {
        parent::setUp();$this->platform=Company::factory()->create();$this->seller=Company::factory()->create();config(['app.master_company_id'=>$this->platform->id]);
    }
    private function actor(Company $company,array $permissions,string $status='active'): User {
        $u=User::factory()->create(['company_id'=>$company->id,'active_company_id'=>$company->id]);$u->companies()->syncWithoutDetaching([$company->id=>['status'=>$status]]);
        setPermissionsTeamId($company->id);foreach($permissions as $name)$u->givePermissionTo(Permission::firstOrCreate(['name'=>$name,'guard_name'=>'web']));
        $this->actingAs($u,'sanctum');return $u;
    }
    public function test_public_contact_is_anonymous_and_returns_exactly_three_fields(): void {
        $data=$this->getJson('/api/v1/store/public/contact')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing(['email','phone','address'],array_keys($data));$this->assertSame('محافظة الشرقية، مصر',$data['address']);
        $this->assertSame('support@hwnix.com',$data['email']);$this->assertSame('01007888009',$data['phone']);
    }
    public function test_guest_cannot_access_the_admin_form_or_update(): void {
        $this->getJson('/api/v1/admin/store/contact')->assertUnauthorized();$this->patchJson('/api/v1/admin/store/contact',$this->changed)->assertUnauthorized();
    }
    public function test_super_admin_can_edit_independent_of_selected_company(): void {
        $this->actor($this->seller,['admin.super']);$this->getJson('/api/v1/admin/store/contact')->assertOk();
        $this->patchJson('/api/v1/admin/store/contact',$this->changed)->assertOk()->assertJsonPath('data.email','contact@example.test');
        $this->getJson('/api/v1/store/public/contact')->assertOk()->assertJsonPath('data.address','القاهرة، مصر');
    }
    public function test_delegate_can_edit_without_any_other_store_setting_permission(): void {
        $this->actor($this->platform,['store.contact.manage']);
        $this->getJson('/api/v1/admin/store/contact')->assertOk();$this->patchJson('/api/v1/admin/store/contact',$this->changed)->assertOk();
    }
    public function test_company_admin_needs_explicit_delegation_even_in_platform_company(): void {
        $this->actor($this->platform,['admin.company','store.settings.manage']);
        $this->getJson('/api/v1/admin/store/contact')->assertForbidden();$this->patchJson('/api/v1/admin/store/contact',$this->changed)->assertForbidden();
    }
    public function test_platform_company_admin_with_explicit_contact_delegation_can_edit(): void {
        $this->actor($this->platform,['admin.company','store.contact.manage']);$this->patchJson('/api/v1/admin/store/contact',$this->changed)->assertOk();
    }
    public function test_seller_admin_cannot_edit_even_with_a_same_named_permission(): void {
        $this->actor($this->seller,['admin.company','store.contact.manage']);
        $this->getJson('/api/v1/admin/store/contact')->assertForbidden();$this->patchJson('/api/v1/admin/store/contact',$this->changed)->assertForbidden();
    }
    public function test_suspended_delegate_cannot_edit_despite_retaining_permission(): void {
        $this->actor($this->platform,['store.contact.manage'],'suspended');$this->patchJson('/api/v1/admin/store/contact',$this->changed)->assertForbidden();
    }
    public function test_contact_is_independent_of_company_data_and_selected_company(): void {
        $before=$this->getJson('/api/v1/store/public/contact')->assertOk()->json('data');
        $this->platform->update(['address'=>'عنوان مختلف','email'=>'private@example.test','phone'=>'0552222222']);
        $this->seller->update(['address'=>'عنوان المخزن التفصيلي']);$this->actor($this->seller,['admin.company']);
        $this->assertSame($before,$this->getJson('/api/v1/store/public/contact')->assertOk()->json('data'));
        $this->assertDatabaseCount('store_settings',1);
    }
    public function test_patch_cannot_modify_other_settings_or_company_records(): void {
        $this->actor($this->platform,['store.contact.manage']);StoreSetting::create(['key'=>'marketplace','value'=>['enabled'=>false]]);
        $before=$this->seller->fresh()->getAttributes();
        $this->patchJson('/api/v1/admin/store/contact',$this->changed+['settings'=>['enabled'=>true],'company_id'=>$this->seller->id,'mail_password'=>'never-public'])->assertOk();
        $this->assertSame(['enabled'=>false],StoreSetting::where('key','marketplace')->value('value'));$this->assertSame($before,$this->seller->fresh()->getAttributes());
        $this->assertEqualsCanonicalizing(['email','phone','address'],array_keys(StoreSetting::where('key','store_public_contact')->value('value')));
    }
    public function test_invalid_contact_does_not_partially_overwrite_saved_values(): void {
        $this->actor($this->platform,['store.contact.manage']);$before=$this->getJson('/api/v1/store/public/contact')->json('data');
        foreach([['email'=>'not-an-email'],['phone'=>'javascript:alert(1)'],['address'=>'']] as $invalid){$this->patchJson('/api/v1/admin/store/contact',array_replace($this->changed,$invalid))->assertUnprocessable();}
        $this->assertSame($before,$this->getJson('/api/v1/store/public/contact')->assertOk()->json('data'));
    }
}
