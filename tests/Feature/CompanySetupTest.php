<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\{User,Company,InvoiceType};
use Modules\Guidance\Models\CompanySetupState;
use Spatie\Permission\Models\Permission;
class CompanySetupTest extends TestCase {
    use RefreshDatabase;
    private User $owner;private Company $company;
    protected function setUp(): void {
        parent::setUp();
        $this->owner=User::factory()->create();
        $this->company=Company::factory()->create(['created_by'=>$this->owner->id,'phone'=>'01012345678','address'=>'عنوان الشركة']);
        $this->owner->update(['company_id'=>$this->company->id,'active_company_id'=>$this->company->id]);
        $this->owner->companies()->syncWithoutDetaching([$this->company->id=>['status'=>'active']]);
        setPermissionsTeamId($this->company->id);
        $this->owner->givePermissionTo(Permission::firstOrCreate(['name'=>'admin.company','guard_name'=>'web']));
        $plan=\App\Models\Plan::factory()->create(['company_id'=>null,'created_by'=>$this->owner->id,'max_products'=>100,'max_invoices'=>100,'max_users'=>100]);
        \App\Models\CompanySubscription::create(['company_id'=>$this->company->id,'plan_id'=>$plan->id,'status'=>'active','starts_at'=>now()->subDay(),'ends_at'=>now()->addMonth(),'created_by'=>$this->owner->id]);
        $this->actingAs($this->owner,'sanctum');
    }
    private function getSetup(): array {return $this->getJson('/api/v1/guidance/setup')->assertOk()->json('data');}
    private function save(string $step,array $data=[],?string $revision=null,bool $defer=false){return $this->putJson('/api/v1/guidance/setup/steps/'.$step,['revision'=>$revision??$this->getSetup()['revision'],'data'=>$data,'defer'=>$defer]);}
    public function test_read_only_loading_does_not_change_existing_company_or_create_progress(): void {
        $before=$this->company->fresh()->getAttributes();$r=$this->getSetup();
        $this->assertTrue($r['can_manage']);$this->assertFalse($r['state']['finished']);
        $this->assertSame($before,$this->company->fresh()->getAttributes());$this->assertDatabaseCount('company_setup_states',0);
    }
    public function test_save_and_resume_real_company_fields_without_mass_assignment(): void {
        $this->save('company',['name'=>'شركة جاهزة','phone'=>'01022222222','address'=>'عنوان صحيح','created_by'=>999,'company_id'=>999])->assertOk()->assertJsonPath('data.state.current_step','locations');
        $this->assertSame('شركة جاهزة',$this->company->fresh()->name);$this->assertSame($this->owner->id,$this->company->fresh()->created_by);
        $this->assertContains('company',$this->getSetup()['state']['reviewed']);
    }
    public function test_invalid_form_does_not_advance_or_save_part_of_the_data(): void {
        $name=$this->company->name;$this->save('company',['name'=>'لا تحفظني','phone'=>'','address'=>''])->assertUnprocessable();
        $this->assertSame($name,$this->company->fresh()->name);$this->assertDatabaseCount('company_setup_states',0);
    }
    public function test_stale_company_revision_is_rejected_without_overwriting_newer_edit(): void {
        $revision=$this->getSetup()['revision'];$this->company->update(['name'=>'تعديل أحدث']);
        $this->save('company',['name'=>'تعديل قديم','phone'=>'01022222222','address'=>'عنوان'],$revision)->assertConflict();
        $this->assertSame('تعديل أحدث',$this->company->fresh()->name);
    }
    public function test_hide_finish_and_preferences_are_separate_from_readiness(): void {
        $before=$this->getSetup()['ready'];$this->patchJson('/api/v1/guidance/setup/preferences',['hidden'=>true])->assertOk()->assertJsonPath('data.state.hidden',true);
        $this->save('review')->assertOk()->assertJsonPath('data.state.finished',false);
        $this->assertSame($before,$this->getSetup()['ready']);$this->assertTrue($this->getSetup()['state']['hidden']);
    }
    public function test_features_control_steps_and_defer_does_not_fix_store_readiness(): void {
        $this->save('uses',['uses'=>['inventory','sales','store']])->assertOk();
        $this->save('store',[],null,true)->assertOk()->assertJsonPath('data.state.current_step','review');
        $r=$this->getSetup();$this->assertFalse($r['ready']);$this->assertContains('store',$r['state']['deferred']);
        $this->assertTrue(collect($r['issues'])->contains(fn($i)=>$i['step']==='store'));
    }
    public function test_member_can_review_but_cannot_change_configuration_or_read_financial_fields(): void {
        $staff=User::factory()->create(['company_id'=>$this->company->id,'active_company_id'=>$this->company->id]);$staff->companies()->attach($this->company->id,['status'=>'active']);
        $this->actingAs($staff,'sanctum');$r=$this->getSetup();$this->assertFalse($r['can_manage']);$this->assertSame([],$r['data']['cash_boxes']);$this->assertNull($r['data']['store']);
        $this->save('company',['name'=>'مرفوض','phone'=>'01022222222','address'=>'عنوان'])->assertForbidden();
        $this->save('company')->assertOk();$this->assertDatabaseCount('company_setup_states',1);
        $this->actingAs($this->owner,'sanctum');$this->assertSame([],$this->getSetup()['state']['reviewed']);
    }
    public function test_foreign_active_company_is_forbidden_even_for_company_admin(): void {
        $other=Company::factory()->create();$this->owner->update(['active_company_id'=>$other->id]);
        $this->getJson('/api/v1/guidance/setup')->assertForbidden();
    }
    public function test_location_save_reuses_defaults_and_rejects_foreign_warehouse(): void {
        $r=$this->getSetup();$b=$r['data']['branches'][0];$w=$r['data']['warehouses'][0];
        $data=['branch_id'=>$b['id'],'branch_name'=>$b['name'],'warehouse_id'=>$w['id'],'name'=>$w['name'],'location'=>'المخزن بعنوان كامل','latitude'=>30,'longitude'=>31,'location_confirmed'=>true];
        $this->save('locations',$data)->assertOk();$this->save('locations',$data)->assertOk();
        $this->assertSame(1,DB::table('warehouses')->where('company_id',$this->company->id)->count());
        $other=Company::factory()->create(['created_by'=>$this->owner->id]);$foreign=DB::table('warehouses')->where('company_id',$other->id)->first();$data['warehouse_id']=$foreign->id;
        $this->save('locations',$data)->assertNotFound();
    }
    public function test_new_unit_is_real_reusable_and_repeated_save_has_no_duplicate(): void {
        $data=['unit_id'=>null,'unit_name'=>'قطعة إعداد','decimal_places'=>0,'auto_update_purchase_price'=>false];
        $this->save('inventory',$data)->assertOk();$this->save('inventory',$data)->assertOk();
        $this->assertSame(1,DB::table('units')->where('company_id',$this->company->id)->where('name','قطعة إعداد')->count());
        $this->assertNotNull($this->company->fresh()->settings['setup_unit_id']);
    }
    public function test_billing_setup_does_not_invent_invoices_transactions_or_balances(): void {
        $type=InvoiceType::create(['name'=>'بيع','code'=>'sale','context'=>'sales']);
        $before=DB::table('transactions')->count();
        $this->save('billing',['invoice_type_ids'=>[$type->id],'payment_method_id'=>null,'payment_method_name'=>'نقدًا','create_cash_box'=>false,'create_cash_customer'=>true,'print_format'=>'standard','show_logo'=>true,'footer_text'=>'شكرًا'])->assertOk();
        $this->assertDatabaseCount('invoices',0);$this->assertSame($before,DB::table('transactions')->count());
        $this->assertSame('standard',$this->company->fresh()->settings['print_settings']['print_format']);
    }

    public function test_complete_setup_allows_a_real_first_product_and_invoice_without_more_setup(): void {
        $this->save('uses',['uses'=>['inventory','sales']])->assertOk();
        $r=$this->getSetup();$b=$r['data']['branches'][0];$w=$r['data']['warehouses'][0];
        $this->save('locations',['branch_id'=>$b['id'],'branch_name'=>$b['name'],'warehouse_id'=>$w['id'],'name'=>$w['name'],'location'=>'عنوان المخزن الكامل','location_confirmed'=>false])->assertOk();
        $this->save('inventory',['unit_id'=>null,'unit_name'=>'قطعة','decimal_places'=>0,'auto_update_purchase_price'=>false])->assertOk();
        $this->save('billing',['invoice_type_ids'=>[],'create_invoice_types'=>true,'payment_method_id'=>null,'payment_method_name'=>'نقدًا','create_cash_box'=>true,'create_cash_customer'=>true,'print_format'=>'standard','show_logo'=>true,'footer_text'=>''])->assertOk()->assertJsonPath('data.ready',true);
        $r=$this->getSetup();$unit=$this->company->fresh()->settings['setup_unit_id'];
        $this->postJson('/api/v1/products',['name'=>'أول منتج حقيقي','product_type'=>'physical','require_stock'=>true,'base_unit_id'=>$unit,'variants'=>[['sku'=>'FIRST-SETUP','base_unit_id'=>$unit,'retail_price'=>100,'stocks'=>[['warehouse_id'=>$w['id'],'quantity'=>10]]]]])->assertCreated();
        $product=\Modules\Inventory\Models\Product::where('name','أول منتج حقيقي')->firstOrFail();$variant=$product->variants()->firstOrFail();
        $type=InvoiceType::where('company_id',$this->company->id)->where('code','sale')->firstOrFail();
        $response=$this->postJson('/api/v1/invoices',['invoice_type_id'=>$type->id,'invoice_type_code'=>'sale','user_id'=>$this->company->fresh()->default_cash_customer_id,'warehouse_id'=>$w['id'],'cash_box_id'=>$r['data']['cash_boxes'][0]['id'],'payment_method_id'=>$r['data']['payment_methods'][0]['id'],'status'=>'confirmed','gross_amount'=>100,'net_amount'=>100,'paid_amount'=>100,'items'=>[['product_id'=>$product->id,'variant_id'=>$variant->id,'unit_id'=>$unit,'name'=>$product->name,'quantity'=>1,'unit_price'=>100,'total'=>100]]]);
        $response->assertCreated();
        $this->assertDatabaseHas('invoices',['status'=>'paid']);
        $this->assertSame(1,DB::table('daily_sales_summary')->where('company_id',$this->company->id)->count());
        $this->assertDatabaseCount('invoices',1);
    }
    public function test_email_readiness_needs_a_successful_explicit_test_and_resets_when_changed(): void {
        $this->save('uses',['uses'=>['inventory','email']])->assertOk();
        $setting=\Modules\Notification\Models\MailSetting::create(['company_id'=>$this->company->id,'title'=>'البريد','mail_transport'=>'smtp','mail_host'=>'smtp.invalid','mail_port'=>587,'mail_username'=>'test','mail_password'=>'fixture-only','mail_from_address'=>'sender@example.test','mail_from_name'=>'اختبار','is_active'=>true]);
        $this->assertTrue(collect($this->getSetup()['issues'])->contains(fn($i)=>$i['group']==='messages'));
        \Modules\Guidance\Services\SetupConnectionCheck::record('email',$setting);
        $this->assertFalse(collect($this->getSetup()['issues'])->contains(fn($i)=>$i['group']==='messages'));
        $setting->update(['mail_host'=>'changed.invalid']);
        $this->assertTrue(collect($this->getSetup()['issues'])->contains(fn($i)=>$i['group']==='messages'));
        $json=json_encode($this->getSetup());$this->assertStringNotContainsString('fixture-only',$json);$this->assertStringNotContainsString('smtp.invalid',$json);
    }

    public function test_seller_can_submit_settings_and_encrypted_payout_proposal_without_self_approval(): void {
        $this->save('uses',['uses'=>['inventory','store']])->assertOk();$r=$this->getSetup();$w=$r['data']['warehouses'][0];
        $this->save('locations',['branch_id'=>$w['branch_id'],'branch_name'=>'الرئيسي','warehouse_id'=>$w['id'],'name'=>$w['name'],'location'=>'عنوان المخزن','latitude'=>30,'longitude'=>31,'location_confirmed'=>true])->assertOk();
        $data=['warehouse_id'=>$w['id'],'recipient_name'=>'البائع','phone'=>'01012345678','city'=>'القاهرة','governorate_code'=>'cairo','seller_kind'=>'individual','tax_status'=>'not_registered','tax_mode'=>'none','rate_bps'=>0,'basis'=>'إفادة صاحب النشاط','local_enabled'=>true,'radius_km'=>5,'fee'=>15,'free_above'=>null,'payout_type'=>'bank','payout_account_holder'=>'البائع','payout_account_number'=>'fixture-account-123','is_enabled'=>true,'policy_overrides'=>['commission_percent'=>0]];
        $this->save('store',$data)->assertOk()->assertJsonPath('data.ready',false);
        $p=\Modules\Store\Models\StoreSellerProfile::where('company_id',$this->company->id)->firstOrFail();
        $this->assertFalse($p->is_enabled);$this->assertNull($p->payout_destination);$this->assertSame('self_declared',$p->business_profile['document_status']);
        $this->assertSame('fixture-account-123',$p->payout_destination_proposal['account_number']);
        $this->assertStringNotContainsString('fixture-account-123',$p->getRawOriginal('payout_destination_proposal'));
        $this->assertEmpty($p->policy_overrides);$this->assertFalse(app(\Modules\Store\Services\StoreSettingsService::class)->get()['enabled']);
    }
    public function test_failed_delivery_setup_rolls_back_profile_and_personal_progress_together(): void {
        $this->save('uses',['uses'=>['store']])->assertOk();$w=$this->getSetup()['data']['warehouses'][0];
        \Modules\Inventory\Models\Warehouse::whereKey($w['id'])->update(['location'=>'عنوان المخزن']);
        $this->save('store',['warehouse_id'=>$w['id'],'recipient_name'=>'البائع','phone'=>'01012345678','city'=>'القاهرة','governorate_code'=>'cairo','seller_kind'=>'individual','tax_status'=>'not_registered','tax_mode'=>'none','rate_bps'=>0,'basis'=>'إفادة','local_enabled'=>true,'radius_km'=>5,'fee'=>15])->assertUnprocessable();
        $this->assertDatabaseCount('store_seller_profiles',0);$this->assertNotContains('store',$this->getSetup()['state']['reviewed']);
    }
}
