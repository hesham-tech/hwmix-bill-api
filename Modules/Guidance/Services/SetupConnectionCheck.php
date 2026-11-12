<?php
namespace Modules\Guidance\Services;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
final class SetupConnectionCheck {
    private static function fingerprint(Model $setting): string {
        $keys=['mail_transport','mail_host','mail_port','mail_username','mail_password','mail_encryption','mail_from_address','mail_from_name','mail_reply_to_address','mail_reply_to_name','phone_number_id','waba_id','access_token'];
        $data=[];foreach($keys as $key)$data[$key]=(string)($setting->getAttribute($key)??'');
        return hash('sha256',serialize($data));
    }
    public static function record(string $kind,Model $setting): void {
        DB::table('setup_connection_checks')->updateOrInsert(['kind'=>$kind,'setting_id'=>$setting->id],['fingerprint'=>self::fingerprint($setting),'verified_at'=>now()]);
    }
    public static function verified(string $kind,?Model $setting): bool {
        return $setting&&DB::table('setup_connection_checks')->where('kind',$kind)->where('setting_id',$setting->id)->where('fingerprint',self::fingerprint($setting))->exists();
    }
}
