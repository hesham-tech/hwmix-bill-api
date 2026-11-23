<?php
namespace Modules\Store\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\{Cache,Http,RateLimiter};
class StoreMapController extends Controller {
    public function configuration(){
        $key=config('maps.geoapify_map_key');
        return response()->json(['data'=>['search_enabled'=>(bool)config('maps.geoapify_key'),'tile_url'=>$key?'https://maps.geoapify.com/v1/tile/osm-carto/{z}/{x}/{y}.png?apiKey='.rawurlencode($key):null,'attribution'=>$key?'© OpenStreetMap contributors · Powered by Geoapify':null]]);
    }
    public function search(Request $request){
        $v=$request->validate(['q'=>'required|string|min:3|max:160']);
        abort_unless(config('maps.geoapify_key'),503,'البحث عن العنوان غير متاح حاليًا. يمكنك اختيار النقطة على الخريطة.');
        $q=trim(preg_replace('/\s+/u',' ',$v['q']));$cache='map-search:'.hash('sha256',$q);
        if(($result=Cache::get($cache))!==null)return response()->json(['data'=>$result]);
        $day='map-search-count:'.gmdate('Y-m-d');Cache::add($day,0,now()->addDay());
        abort_if((int)Cache::get($day)>=max(0,(int)config('maps.daily_search_limit')),429,'وصل البحث لحد الاستخدام اليومي. استخدم الخريطة أو موقع الجهاز.');
        $result=RateLimiter::attempt('map-provider-outbound',3,function()use($q,$cache,$day){
            try{$count=Cache::lock('map-search-budget',5)->block(2,function()use($day){
                abort_if((int)Cache::get($day)>=(int)config('maps.daily_search_limit'),429,'وصل البحث لحد الاستخدام اليومي.');
                return Cache::increment($day);
            });}catch(\Illuminate\Contracts\Cache\LockTimeoutException $e){abort(429,'انتظر لحظة ثم أعد البحث.');}
            try{$r=Http::timeout(6)->acceptJson()->get('https://api.geoapify.com/v1/geocode/search',['text'=>$q,'filter'=>'countrycode:eg','lang'=>'ar','limit'=>5,'format'=>'json','apiKey'=>config('maps.geoapify_key')]);}
            catch(\Throwable $e){abort(503,'تعذر البحث الآن. حدد مكانك يدويًا؛ بياناتك محفوظة.');}
            abort_unless($r->successful(),503,'تعذر البحث الآن. استخدم الخريطة أو موقع الجهاز.');
            $data=collect($r->json('results')??[])->filter(fn($p)=>is_numeric($p['lat']??null)&&is_numeric($p['lon']??null)&&abs($p['lat'])<=90&&abs($p['lon'])<=180)->take(5)->map(fn($p)=>['label'=>(string)($p['formatted']??$p['name']??'العنوان'),'latitude'=>(float)$p['lat'],'longitude'=>(float)$p['lon']])->values()->all();
            Cache::put($cache,$data,now()->addDay());return $data;
        },1);
        abort_if($result===false,429,'انتظر لحظة ثم أعد البحث.');return response()->json(['data'=>$result]);
    }
}
