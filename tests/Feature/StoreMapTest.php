<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Support\Facades\{Cache,Http,RateLimiter};
class StoreMapTest extends TestCase {
    protected function setUp(): void {parent::setUp();config(['cache.default'=>'array','maps.geoapify_key'=>null,'maps.geoapify_map_key'=>null,'maps.daily_search_limit'=>500]);Cache::flush();RateLimiter::clear('map-provider-outbound');}
    public function test_missing_key_keeps_fallback_available_without_outbound_calls(): void {
        Http::preventStrayRequests();$this->getJson('/api/v1/store/public/map-config')->assertOk()->assertJsonPath('data.search_enabled',false)->assertJsonPath('data.tile_url',null);
        $this->getJson('/api/v1/store/public/map-search?q=القاهرة')->assertStatus(503);Http::assertNothingSent();
    }
    public function test_public_configuration_never_exposes_server_search_key(): void {
        config(['maps.geoapify_key'=>'fixture-private-key']);$r=$this->getJson('/api/v1/store/public/map-config')->assertOk()->assertJsonPath('data.search_enabled',true);$this->assertStringNotContainsString('fixture-private-key',$r->getContent());
    }
    public function test_search_is_scoped_to_egypt_caches_results_and_returns_only_safe_fields(): void {
        config(['maps.geoapify_key'=>'fixture-private-key']);Http::fake(['api.geoapify.com/*'=>Http::response(['results'=>[['formatted'=>'الصالحية الجديدة، مصر','lat'=>30.65,'lon'=>31.9,'private'=>'omit'],['formatted'=>'invalid','lat'=>200,'lon'=>400]]])]);
        $url='/api/v1/store/public/map-search?'.http_build_query(['q'=>'الصالحية الجديدة']);$this->getJson($url)->assertOk()->assertJsonCount(1,'data')->assertJsonStructure(['data'=>[['label','latitude','longitude']]]);$this->getJson($url)->assertOk();Http::assertSentCount(1);
        Http::assertSent(fn($r)=>$r['filter']==='countrycode:eg'&&$r['lang']==='ar'&&$r['limit']===5);$this->assertSame(1,Cache::get('map-search-count:'.gmdate('Y-m-d')));
    }
    public function test_daily_budget_rejects_more_provider_requests_but_cached_results_remain_available(): void {
        config(['maps.geoapify_key'=>'fixture-private-key','maps.daily_search_limit'=>1]);Http::fake(['api.geoapify.com/*'=>Http::response(['results'=>[]])]);$this->getJson('/api/v1/store/public/map-search?q=Cairo')->assertOk();$this->getJson('/api/v1/store/public/map-search?q=Cairo')->assertOk();$this->getJson('/api/v1/store/public/map-search?q=Zagazig')->assertStatus(429);Http::assertSentCount(1);
    }
    public function test_provider_errors_do_not_leak_key_or_break_manual_location(): void {
        config(['maps.geoapify_key'=>'fixture-private-key']);Http::fake(['api.geoapify.com/*'=>Http::response(['message'=>'fixture-private-key'],500)]);$r=$this->getJson('/api/v1/store/public/map-search?q=Cairo')->assertStatus(503);$this->assertStringNotContainsString('fixture-private-key',$r->getContent());$this->getJson('/api/v1/store/public/map-config')->assertOk();
    }
    public function test_short_queries_are_rejected_without_consuming_budget(): void {
        config(['maps.geoapify_key'=>'fixture-private-key']);Http::preventStrayRequests();$this->getJson('/api/v1/store/public/map-search?q=a')->assertUnprocessable();Http::assertNothingSent();
    }
}
