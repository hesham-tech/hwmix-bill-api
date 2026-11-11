<?php
namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Store\Services\{StoreRegistrationLegalService,StoreSettingsService};
use Tests\TestCase;

class StoreRegistrationLegalTest extends TestCase
{
    use RefreshDatabase;

    private int $platform;
    protected function setUp(): void
    {
        parent::setUp();
        $this->platform=Company::withoutEvents(fn()=>Company::factory()->create(['created_by'=>null]))->id;
        config(['app.master_company_id'=>$this->platform]);
    }
    private function document(string $key,string $status='published',?int $company=null): array
    {
        $id=DB::table('legal_documents')->insertGetId(['key'=>$key,'company_id'=>$company,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $version=DB::table('legal_document_versions')->insertGetId(['legal_document_id'=>$id,'version'=>'1.0','title'=>$key,'content'=>'<p>Fixture policy</p>',
            'company_id'=>$company,'status'=>$status,'published_at'=>$status==='published'?now()->subMinute():null,'created_at'=>now(),'updated_at'=>now()]);
        return [$id,$version];
    }
    public function test_explicit_store_selection_replaces_erp_registration_consents(): void
    {
        $this->document('terms-of-use');$this->document('privacy-policy');
        [$terms,$termsVersion]=$this->document('store-terms-of-sale','published',$this->platform);
        [$privacy,$privacyVersion]=$this->document('store-privacy-policy','published',$this->platform);
        [$returns]=$this->document('store-returns-policy','published',$this->platform);
        app(StoreSettingsService::class)->save(['legal_document_ids'=>[$terms,$privacy,$returns]]);
        $documents=app(StoreRegistrationLegalService::class)->documents();
        $this->assertSame([$termsVersion,$privacyVersion],array_column($documents,'id'));
        $this->assertSame(['store-terms-of-sale','store-privacy-policy'],array_column($documents,'key'));
    }
    public function test_unpublished_selected_policy_does_not_fall_back_to_published_erp_policy(): void
    {
        $this->document('terms-of-use');$this->document('privacy-policy');
        [$terms]=$this->document('store-terms-of-sale');[$privacy]=$this->document('store-privacy-policy','draft');
        app(StoreSettingsService::class)->save(['legal_document_ids'=>[$terms,$privacy]]);
        $this->assertSame([],app(StoreRegistrationLegalService::class)->documents());
    }
    public function test_foreign_company_policy_is_not_used_for_platform_registration(): void
    {
        $foreign=Company::withoutEvents(fn()=>Company::factory()->create(['created_by'=>null]))->id;
        [$terms]=$this->document('store-terms-of-sale','published',$foreign);
        [$privacy]=$this->document('store-privacy-policy','published',$foreign);
        app(StoreSettingsService::class)->save(['legal_document_ids'=>[$terms,$privacy]]);
        $this->assertSame([],app(StoreRegistrationLegalService::class)->documents());
    }
    public function test_legacy_registration_remains_available_before_store_activation_only(): void
    {
        [, $terms]=$this->document('terms-of-use');[, $privacy]=$this->document('privacy-policy');
        $this->document('store-terms-of-sale');$this->document('store-privacy-policy');
        $this->assertSame([$terms,$privacy],array_column(app(StoreRegistrationLegalService::class)->documents(),'id'));
        app(StoreSettingsService::class)->save(['enabled'=>true]);
        $this->assertSame([],app(StoreRegistrationLegalService::class)->documents());
    }

    public function test_published_store_policies_do_not_create_erp_pending_acceptances(): void {
        [, $erp]=$this->document('terms-of-use','published',$this->platform);
        $this->document('store-terms-of-sale','published',$this->platform);
        $this->document('store-privacy-policy','published',$this->platform);
        $this->document('store-returns-policy','published',$this->platform);
        $user=\App\Models\User::factory()->create(['company_id'=>$this->platform,'active_company_id'=>$this->platform]);
        $result=$this->actingAs($user,'sanctum')->getJson('/api/v1/legal/acceptances/pending')->assertOk()->json('data');
        $this->assertSame([$erp],array_column($result,'id'));
    }
}
