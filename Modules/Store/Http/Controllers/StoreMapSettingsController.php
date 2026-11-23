<?php
namespace Modules\Store\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Modules\Store\Services\{StoreAccess,StoreMapSettings};
class StoreMapSettingsController extends Controller {
 private function authorizeEditor(Request $r): void {
  app(StoreAccess::class)->platform('store.maps.manage');$u=$r->user();
  if(!$u->checkPermissionTo('admin.super'))abort_unless($u->companies()->where('companies.id',$u->active_company_id)->wherePivot('status','active')->exists(),403);
 }
 public function show(Request $r){$this->authorizeEditor($r);return response()->json(['data'=>app(StoreMapSettings::class)->publicAdmin()]);}
 public function update(Request $r){
  $this->authorizeEditor($r);$v=$r->validate(['enabled'=>'required|boolean','daily_limit'=>'required|integer|min:0|max:3000','revision'=>'required|string|max:80','search_key'=>['nullable','string','max:256','regex:/^[a-zA-Z0-9_-]+$/'],'map_key'=>['nullable','string','max:256','regex:/^[a-zA-Z0-9_-]+$/'],'clear_search_key'=>'sometimes|boolean','clear_map_key'=>'sometimes|boolean']);
  return response()->json(['data'=>app(StoreMapSettings::class)->save($v),'message'=>'حُفظت إعدادات الخرائط.']);
 }
 public function test(Request $r){
  $this->authorizeEditor($r);$s=app(StoreMapSettings::class)->get();abort_unless(!empty($s['search_key']),422,'احفظ مفتاح البحث أولًا.');
  try{$response=Http::timeout(6)->acceptJson()->get('https://api.geoapify.com/v1/geocode/search',['text'=>'القاهرة، مصر','filter'=>'countrycode:eg','lang'=>'ar','limit'=>1,'format'=>'json','apiKey'=>$s['search_key']]);}
  catch(\Throwable $e){abort(503,'تعذر الاتصال بالمزود. أعد المحاولة.');}
  abort_unless($response->successful()&&is_array($response->json('results')),422,'فشل اختبار البحث. راجع المفتاح وقيوده وحصة الحساب.');
  return response()->json(['message'=>'نجح الاتصال بخدمة البحث.']);
 }
}
