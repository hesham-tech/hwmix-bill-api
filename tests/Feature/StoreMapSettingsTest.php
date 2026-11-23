<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Company,User};
use Modules\Store\Models\StoreSetting;
use Spatie\Permission\Models\Permission;
class StoreMapSettingsTest extends TestCase {
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
    private function payload(): array {return ['enabled'=>true,'daily_limit'=>500,'revision'=>app(\Modules\Store\Services\StoreMapSettings::class)->get()['revision'],'search_key'=>'fixture-secret'];}
    public function test_root_and_explicit_delegate_can_save_without_exposing_keys(): void {
      foreach([['admin.super'],['store.maps.manage']] as $perms){$this->actor($this->platform,$perms);$r=$this->patchJson('/api/v1/admin/store/maps',$this->payload())->assertOk()->assertJsonPath('data.search_key_configured',true);$this->assertStringNotContainsString('fixture-secret',$r->getContent());$this->assertStringNotContainsString('fixture-secret',json_encode(StoreSetting::where('key','map_integration')->value('value')));}
    }
    public function test_seller_and_ordinary_platform_admin_and_suspended_delegate_are_denied(): void {
      foreach([[$this->seller,['admin.company','store.maps.manage'],'active'],[$this->platform,['admin.company','store.settings.manage'],'active'],[$this->platform,['store.maps.manage'],'suspended']] as [$c,$p,$status]){$this->actor($c,$p,$status);$this->getJson('/api/v1/admin/store/maps')->assertForbidden();$this->patchJson('/api/v1/admin/store/maps',$this->payload())->assertForbidden();$this->postJson('/api/v1/admin/store/maps/test')->assertForbidden();}
    }
    public function test_guest_is_denied(): void {$this->getJson('/api/v1/admin/store/maps')->assertUnauthorized();$this->postJson('/api/v1/admin/store/maps/test')->assertUnauthorized();}
    public function test_blank_preserves_clear_is_explicit_and_stale_edits_are_rejected(): void {
      $this->actor($this->platform,['store.maps.manage']);$v=$this->payload();$this->patchJson('/api/v1/admin/store/maps',$v)->assertOk();$this->patchJson('/api/v1/admin/store/maps',$v)->assertConflict();$v=$this->payload();unset($v['search_key']);$this->patchJson('/api/v1/admin/store/maps',$v+['search_key'=>null])->assertOk()->assertJsonPath('data.search_key_configured',true);$v=$this->payload();unset($v['search_key']);$this->patchJson('/api/v1/admin/store/maps',array_replace($v,['enabled'=>false,'clear_search_key'=>true]))->assertOk()->assertJsonPath('data.search_key_configured',false);$this->getJson('/api/v1/store/public/map-config')->assertJsonPath('data.search_enabled',false);
    }
    public function test_saved_settings_override_environment_and_test_errors_are_sanitized(): void {
      config(['maps.geoapify_key'=>'legacy-secret']);$this->actor($this->platform,['store.maps.manage']);$this->patchJson('/api/v1/admin/store/maps',$this->payload())->assertOk();\Illuminate\Support\Facades\Http::fake(['api.geoapify.com/*'=>\Illuminate\Support\Facades\Http::response(['results'=>[]])]);$this->postJson('/api/v1/admin/store/maps/test')->assertOk();\Illuminate\Support\Facades\Http::assertSent(fn($r)=>$r['apiKey']==='fixture-secret');$this->getJson('/api/v1/store/public/map-config')->assertOk()->assertDontSee('fixture-secret');
    }
    public function test_invalid_and_unrelated_settings_do_not_change_saved_state(): void {
      $this->actor($this->platform,['store.maps.manage']);$this->patchJson('/api/v1/admin/store/maps',array_replace($this->payload(),['daily_limit'=>-1]))->assertUnprocessable();$this->assertNull(StoreSetting::where('key','map_integration')->first());$this->patchJson('/api/v1/admin/store/maps',$this->payload()+['company_id'=>999,'enabled_checkout'=>true])->assertOk();$this->assertNull(StoreSetting::where('key','marketplace')->first());
    }
    public function test_provider_failure_does_not_echo_secrets_and_disabled_settings_override_environment(): void {
      $this->actor($this->platform,['store.maps.manage']);$this->patchJson('/api/v1/admin/store/maps',$this->payload())->assertOk();
      \Illuminate\Support\Facades\Http::fake(['api.geoapify.com/*'=>\Illuminate\Support\Facades\Http::response(['message'=>'fixture-secret'],401)]);
      $this->postJson('/api/v1/admin/store/maps/test')->assertUnprocessable()->assertDontSee('fixture-secret');
      $v=$this->payload();$this->patchJson('/api/v1/admin/store/maps',array_replace($v,['enabled'=>false]))->assertOk();config(['maps.geoapify_key'=>'legacy-secret']);$this->getJson('/api/v1/store/public/map-config')->assertJsonPath('data.search_enabled',false);$this->getJson('/api/v1/store/public/map-search?q=Cairo')->assertStatus(503);
    }

}
