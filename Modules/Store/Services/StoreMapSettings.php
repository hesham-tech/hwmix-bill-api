<?php
namespace Modules\Store\Services;
use Illuminate\Support\Facades\{Crypt,DB};
use Modules\Store\Models\StoreSetting;
class StoreMapSettings {
 public function get(): array {
  $s=StoreSetting::where('key','map_integration')->value('value');
  if($s===null)return ['enabled'=>(bool)config('maps.geoapify_key'),'search_key'=>config('maps.geoapify_key'),'map_key'=>config('maps.geoapify_map_key'),'daily_limit'=>(int)config('maps.daily_search_limit',500),'revision'=>'legacy'];
  foreach(['search_key','map_key'] as $k)$s[$k]=empty($s[$k])?null:Crypt::decryptString($s[$k]);
  return $s;
 }
 public function publicAdmin(): array {
  $s=$this->get();return ['enabled'=>(bool)$s['enabled'],'daily_limit'=>$s['daily_limit'],'revision'=>$s['revision'],'search_key_configured'=>!empty($s['search_key']),'map_key_configured'=>!empty($s['map_key'])];
 }
 public function save(array $v): array {
  return DB::transaction(function()use($v){
   // Serialize edits even before the first settings row is created.
   DB::table('permissions')->where('name','store.maps.manage')->lockForUpdate()->first();
   $s=$this->get();abort_unless(hash_equals($s['revision'],$v['revision']),409,'تغيرت الإعدادات. أعد تحميلها قبل الحفظ.');
   foreach(['search_key','map_key'] as $k){if($v['clear_'.$k]??false)$s[$k]=null;elseif(!empty($v[$k]))$s[$k]=$v[$k];}
   $s['enabled']=$v['enabled'];$s['daily_limit']=$v['daily_limit'];
   abort_if($s['enabled']&&empty($s['search_key']),422,'أدخل مفتاح البحث قبل تفعيل الخدمة.');
   $s['revision']=(string)\Illuminate\Support\Str::uuid();
   foreach(['search_key','map_key'] as $k)$s[$k]=empty($s[$k])?null:Crypt::encryptString($s[$k]);
   StoreSetting::updateOrCreate(['key'=>'map_integration'],['value'=>$s]);return $this->publicAdmin();
  });
 }
}
