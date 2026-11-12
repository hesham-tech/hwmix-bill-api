<?php
namespace Modules\Store\Services;
use Illuminate\Validation\ValidationException;
use Modules\Store\Models\StoreSellerProfile;
use Modules\Store\Models\StoreShippingAccount;
use Modules\Store\Models\StoreShippingRate;
class StoreShippingQuoteService
{
    public function __construct(private StoreSettingsService $settings) {}
    public function quote(int $companyId,string|array $destination,string $paymentMethod,int $subtotal): array {
        $profile=StoreSellerProfile::where('company_id',$companyId)->where('is_enabled',true)->first();
        if(!$profile||$profile->policy_review_required||!$profile->warehouse_id||empty($profile->pickup_address['street'])||empty($profile->pickup_address['phone'])
            ||!\Modules\Inventory\Models\Warehouse::withoutGlobalScopes()->where('company_id',$companyId)->whereKey($profile->warehouse_id)->exists())
            throw ValidationException::withMessages(['shipping'=>'بيانات استلام شحنة البائع لم تكتمل بعد.']);
        $address=is_array($destination)?$destination:['governorate_code'=>$destination];$governorate=$address['governorate_code'];
        $local=app(LocalDeliveryService::class)->quote($profile,$address,$subtotal);if($local)return $local;
        $own=StoreShippingAccount::where('company_id',$companyId)->where('is_active',true)
            ->when($profile->shipping_account_id,fn($q)=>$q->whereKey($profile->shipping_account_id))->orderBy('id')->first();
        $fallback=StoreShippingAccount::where('company_id',config('app.master_company_id',1))
            ->where('is_active',true)->find($this->settings->get()['platform_shipping_account_id']);
        foreach(collect([$own,$fallback])->filter()->unique('id') as $account) {
            if(app()->environment('production')&&$account->is_test_mode) continue;
            $rate=StoreShippingRate::where('shipping_account_id',$account->id)->where('governorate_code',$governorate)
                ->whereIn('scope_company_id',[0,$companyId])->orderByDesc('scope_company_id')->first();
            if(!$rate||!$rate->is_active||($paymentMethod==='cod'&&!$rate->cod_allowed)) continue;
            $fee=$rate->free_above_minor!==null&&$subtotal>=$rate->free_above_minor?0:$rate->fee_minor;
            $base=$rate->scope_company_id>0?StoreShippingRate::where('shipping_account_id',$account->id)->where('governorate_code',$governorate)->where('scope_company_id',0)->first():null;
            $platform=(int)$account->company_id===(int)config('app.master_company_id',1);
            return [
                'delivery_method'=>'carrier','routing_version'=>(int)$profile->local_delivery_version,'shipping_account_id'=>$account->id,'carrier_name'=>$account->name,'driver'=>$account->driver,
                'fee_minor'=>$fee,'rate_id'=>$rate->id,'governorate_code'=>$governorate,
                'carrier_fee_minor'=>$rate->carrier_fee_minor??$base?->carrier_fee_minor??$base?->fee_minor??$rate->fee_minor,'subsidy_payer'=>$rate->subsidy_payer??'seller',
                'cod_beneficiary'=>$account->cod_beneficiary,'shipping_fee_beneficiary'=>$platform?'platform':'seller',
                'pickup_address'=>app(WarehousePickupService::class)->address($profile),'warehouse_id'=>$profile->warehouse_id,
                'integration'=>['driver'=>$account->driver,'credentials'=>$account->credentials??[],
                    'configuration'=>$account->configuration??[],'is_test_mode'=>$account->is_test_mode],
            ];
        }
        $local=app(LocalDeliveryService::class)->quote($profile,$address,$subtotal,true);if($local)return $local;
        throw ValidationException::withMessages(['shipping'=>'البائع #'.$companyId.' لا يخدم هذا العنوان بطريقة توصيل مسعرة؛ عدل السلة أو العنوان.']);
    }
}
