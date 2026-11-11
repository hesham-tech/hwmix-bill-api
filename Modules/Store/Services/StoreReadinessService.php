<?php
namespace Modules\Store\Services;
use Modules\Store\Models\StoreSellerProfile;
use Modules\Notification\Services\NotificationIntegrationResolver;
class StoreReadinessService {
    public function issues(?array $settings=null): array {
        $settings=$settings??app(StoreSettingsService::class)->get(); $issues=[];
        if($settings['allow_guest_checkout'])$issues[]='إلزام حساب العميل للشراء؛ فوترة شراء الضيف لم تجهز للإطلاق بعد.';
        if(app()->environment('production')) {
            if(config('app.debug'))$issues[]='تعطيل APP_DEBUG قبل فتح المتجر للإنتاج.';
            foreach(['رابط API'=>config('app.url'),'رابط المتجر'=>config('store.frontend_url')] as $label=>$url)
                if(parse_url((string)$url,PHP_URL_SCHEME)!=='https'||!parse_url((string)$url,PHP_URL_HOST))$issues[]='ضبط '.$label.' بعنوان HTTPS صالح للإنتاج.';
        }
        if(!$settings['legal_setup_confirmed']) $issues[]='اعتماد الإعداد القانوني والضريبي.';
        if($settings['tax_mode']==='unconfigured') $issues[]='اختيار أسلوب احتساب الضريبة وقيمتها.';
        if(!app(StoreQuoteService::class)->documents()) $issues[]='اختيار ونشر وثائق الشراء والخصوصية والمرتجعات.';
        if((int)($settings['return_policy']['change_of_mind_days']??0)<14||(int)($settings['return_policy']['defect_days']??0)<30) $issues[]='ضبط مدد المرتجعات: 14 يومًا لتغيير الرأي و30 يومًا للعيب على الأقل.';
        if(!app(NotificationIntegrationResolver::class)->mail()) $issues[]='تفعيل إعداد بريد المنصة لإرسال رموز التوثيق والإشعارات.';
        if(!$settings['cod_enabled']&&!$settings['online_enabled']) $issues[]='تفعيل طريقة دفع واحدة على الأقل.';
        if($settings['online_enabled']&&!app(StorePaymentService::class)->gateways()) $issues[]='إضافة حساب دفع مفعل مكتمل الإعداد ومناسب لبيئة التشغيل.';
        $profiles=StoreSellerProfile::where('is_enabled',true)->get();
        $issues=array_merge($issues,app(StoreOperationalHealth::class)->issues());
        if($profiles->isEmpty()) $issues[]='تجهيز بائع واحد على الأقل ومخزن وعنوان استلام وخدمة شحن مسعرة.';
        foreach($profiles as $profile) {
            if($settings['tax_mode']==='per_seller') {
                try {app(StoreTaxService::class)->resolve((int)$profile->company_id,0,$settings);}
                catch(\Illuminate\Validation\ValidationException $error){$issues[]='تسجيل المعالجة الضريبية للشركة #'.$profile->company_id;}
            }
            if($profile->policy_review_required) $issues[]='مراجعة استثناءات سياسة الشركة #'.$profile->company_id;
            if(!$this->hasShippingRoute($profile,$settings)) $issues[]='إضافة أسعار الشحن للمحافظات المدعومة للشركة #'.$profile->company_id;
            if(!$profile->warehouse_id||empty($profile->pickup_address['street'])||empty($profile->pickup_address['phone'])
                ||!\Modules\Inventory\Models\Warehouse::withoutGlobalScopes()->where('company_id',$profile->company_id)->whereKey($profile->warehouse_id)->exists())
                $issues[]='استكمال مخزن وعنوان استلام الشركة #'.$profile->company_id;
            if(!app(SellerPolicyService::class)->canPublish($profile->company_id))
                $issues[]='تفعيل اشتراك وميزة نشر المتجر للشركة #'.$profile->company_id;
            if(!app(StoreInvoiceBridge::class)->invoiceType($profile->company_id))
                $issues[]='تفعيل نوع فاتورة المبيعات للشركة #'.$profile->company_id;
        }
        return $issues;
    }
    private function hasShippingRoute(StoreSellerProfile $profile,array $settings): bool {
        $rates=\Illuminate\Support\Facades\DB::table('store_shipping_rates as r')->join('store_shipping_accounts as a','a.id','=','r.shipping_account_id')
            ->where('a.is_active',true)->where('r.is_active',true)->whereIn('r.governorate_code',array_keys(StoreGeography::governorates()))->whereIn('r.scope_company_id',[0,$profile->company_id])
            ->where(fn($owner)=>$owner->where(fn($own)=>$own->where('a.company_id',$profile->company_id)->when($profile->shipping_account_id,fn($id)=>$id->where('a.id',$profile->shipping_account_id)))
                ->orWhere(fn($fallback)=>$fallback->where('a.company_id',config('app.master_company_id',1))->where('a.id',$settings['platform_shipping_account_id'])))
            ->where(fn($scope)=>$scope->where('r.scope_company_id',$profile->company_id)->orWhere(fn($base)=>$base->where('r.scope_company_id',0)->whereNotExists(fn($exception)=>$exception->selectRaw('1')->from('store_shipping_rates as e')
                ->whereColumn('e.shipping_account_id','r.shipping_account_id')->whereColumn('e.governorate_code','r.governorate_code')->where('e.scope_company_id',$profile->company_id))));
        if(app()->environment('production'))$rates->where('a.is_test_mode',false);
        if(!$settings['online_enabled'])$rates->where('r.cod_allowed',true);
        return $rates->exists();
    }
}
