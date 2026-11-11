<?php
namespace Modules\Store\Services;

use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Store\Models\StoreSellerProfile;

class LocalDeliveryService
{
    public function defaults(): array {return ['enabled'=>false,'radius_meters'=>null,'fee_minor'=>0,'free_above_minor'=>null];}
    private function configuration(StoreSellerProfile $p): array {
        $address=app(WarehousePickupService::class)->address($p);
        return array_replace($this->defaults(),$p->local_delivery??[],['pickup_latitude'=>$address['latitude'],'pickup_longitude'=>$address['longitude'],'pickup_confirmed'=>$address['location_confirmed']]);
    }
    public function available(StoreSellerProfile $p): bool {return $this->complete($this->configuration($p));}
    public function settings(int $companyId): array {
        $p=StoreSellerProfile::where('company_id',$companyId)->first();
        return ['settings'=>$p?$this->configuration($p):$this->defaults(),'version'=>$p?->local_delivery_version??1,
            'pickup_address'=>$p?app(WarehousePickupService::class)->address($p):[],'warehouse_id'=>$p?->warehouse_id];
    }
    public function save(int $companyId,array $data): array {
        return DB::transaction(function()use($companyId,$data){
            $p=StoreSellerProfile::where('company_id',$companyId)->lockForUpdate()->first();
            if(!$p)throw ValidationException::withMessages(['local_delivery'=>'تحتاج الإدارة إلى إعداد ملف البائع ومخزن التنفيذ أولًا.']);
            if($data['enabled']&&(!$p->warehouse_id||empty($p->pickup_address['street'])||empty($p->pickup_address['phone'])))
                throw ValidationException::withMessages(['local_delivery'=>'أكمل المخزن وعنوان استلام الطلبات أولًا.']);
            if($data['enabled']&&!$this->complete(array_replace($this->configuration($p),['enabled'=>true,'radius_meters'=>1])))
                throw ValidationException::withMessages(['warehouse'=>'حدد إحداثيات مخزن التنفيذ وأكدها من شاشة المخازن أولًا.']);
            $p->update(['local_delivery'=>['enabled'=>(bool)$data['enabled'],
                'radius_meters'=>isset($data['radius_km'])?(int)round((float)$data['radius_km']*1000):null,
                'fee_minor'=>Money::minor((string)($data['fee']??'0')),'free_above_minor'=>isset($data['free_above'])?Money::minor((string)$data['free_above']):null],
                'local_delivery_version'=>(int)$p->local_delivery_version+1]);
            return $this->settings($companyId);
        },3);
    }
    public static function distance(float $lat1,float $lon1,float $lat2,float $lon2): float {
        $a=sin(deg2rad($lat2-$lat1)/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin(deg2rad($lon2-$lon1)/2)**2;
        return 6371000*2*asin(sqrt(max(0,min(1,$a))));
    }
    public function complete(array $settings): bool {
        return !empty($settings['enabled'])&&!empty($settings['pickup_confirmed'])&&is_numeric($settings['pickup_latitude']??null)&&is_numeric($settings['pickup_longitude']??null)
            &&abs((float)$settings['pickup_latitude'])<=90&&abs((float)$settings['pickup_longitude'])<=180&&(int)($settings['radius_meters']??0)>0;
    }
    public function quote(StoreSellerProfile $profile,array $address,int $goodsGross): ?array {
        $s=$this->configuration($profile);
        if(!$s['enabled'])return null;
        if(!$this->complete($s))throw ValidationException::withMessages(['shipping'=>'إعداد نطاق التوصيل الذاتي للبائع غير مكتمل.']);
        if(!is_numeric($address['latitude']??null)||!is_numeric($address['longitude']??null)||!($address['location_confirmed']??false))
            throw ValidationException::withMessages(['delivery_location'=>'حدد موقع عنوان التسليم على الخريطة وأكده لحساب التوصيل.']);
        $distance=self::distance((float)$s['pickup_latitude'],(float)$s['pickup_longitude'],(float)$address['latitude'],(float)$address['longitude']);
        if($distance>(int)$s['radius_meters'])return null;
        return ['delivery_method'=>'seller_local','shipping_account_id'=>null,'carrier_name'=>'توصيل البائع','driver'=>'seller_local',
            'fee_minor'=>$s['free_above_minor']!==null&&$goodsGross>=(int)$s['free_above_minor']?0:(int)$s['fee_minor'],
            'carrier_fee_minor'=>0,'subsidy_payer'=>'seller','rate_id'=>null,'governorate_code'=>$address['governorate_code'],
            'cod_beneficiary'=>'seller','shipping_fee_beneficiary'=>'seller','warehouse_id'=>$profile->warehouse_id,'pickup_address'=>app(WarehousePickupService::class)->address($profile),
            'routing_version'=>(int)$profile->local_delivery_version,
            'local_snapshot'=>['pickup_latitude'=>$s['pickup_latitude'],'pickup_longitude'=>$s['pickup_longitude'],'radius_meters'=>$s['radius_meters'],'distance_meters'=>$distance,
                'latitude'=>$address['latitude'],'longitude'=>$address['longitude'],'fee_minor'=>$s['fee_minor'],'free_above_minor'=>$s['free_above_minor']],
            'integration'=>['driver'=>'seller_local']];
    }
}
