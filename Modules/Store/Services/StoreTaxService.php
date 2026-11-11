<?php
namespace Modules\Store\Services;

use Illuminate\Validation\ValidationException;
use Modules\Store\Models\StoreSellerProfile;

/** Store-only VAT treatment; never changes the seller's ERP taxes or cash boxes. */
class StoreTaxService
{
    public function defaults(): array {
        return ['status'=>'unconfigured','mode'=>'none','rate_bps'=>0,'registration_number'=>null,'basis'=>null,'product_rules'=>[]];
    }
    public function resolve(int $companyId,int $productId,?array $settings=null): array {
        $settings??=app(StoreSettingsService::class)->get();
        if($settings['tax_mode']!=='per_seller') {
            if($settings['tax_mode']==='unconfigured') $this->fail();
            return ['source'=>'legacy_platform','status'=>'legacy','mode'=>$settings['tax_mode'],
                'rate_bps'=>$settings['tax_mode']==='none'?0:(int)$settings['default_tax_rate_bps']];
        }
        $profile=array_replace($this->defaults(),StoreSellerProfile::where('company_id',$companyId)->value('tax_profile')??[]);
        if(!in_array($profile['status'],['not_registered','registered','exempt'],true)||empty($profile['basis'])) $this->fail();
        if($profile['status']==='registered'&&(empty($profile['registration_number'])||!in_array($profile['mode'],['included','exclusive'],true))) $this->fail();
        $mode=$profile['status']==='registered'?$profile['mode']:'none';
        $rate=$mode==='none'?0:(int)$profile['rate_bps'];
        $treatment=$profile['status'];$basis=$profile['basis'];
        foreach($profile['product_rules'] as $rule) if((int)$rule['product_id']===$productId) {
            // An unregistered seller cannot turn VAT collection on by a product exception.
            if($profile['status']!=='registered') $this->fail();
            $treatment=$rule['treatment'];$basis=$rule['basis'];
            $rate=$treatment==='taxable'?(int)$rule['rate_bps']:0;
            $mode=$treatment==='exempt'?'none':$profile['mode'];
            break;
        }
        if(!in_array($mode,['none','included','exclusive'],true)||$rate<0||$rate>10000) $this->fail();
        return ['source'=>'seller','company_id'=>$companyId,'status'=>$profile['status'],'treatment'=>$treatment,
            'mode'=>$mode,'rate_bps'=>$rate,'basis'=>$basis,'registration_number'=>$profile['registration_number']];
    }
    public function calculate(int $line,array $treatment): array {
        $rate=(int)$treatment['rate_bps'];$mode=$treatment['mode'];
        $tax=$mode==='exclusive'
            ?(int)bcdiv(bcadd(bcmul((string)$line,(string)$rate,0),'5000',0),'10000',0)
            :($mode==='included'?$line-(int)bcdiv(bcadd(bcmul((string)$line,'10000',0),(string)intdiv(10000+$rate,2),0),(string)(10000+$rate),0):0);
        return ['tax'=>$tax,'gross'=>$mode==='exclusive'?$line+$tax:$line];
    }
    private function fail(): never {
        throw ValidationException::withMessages(['tax_profile'=>'المعالجة الضريبية لأحد البائعين غير مكتملة؛ راجع الإدارة.']);
    }
}
