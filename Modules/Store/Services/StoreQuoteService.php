<?php
namespace Modules\Store\Services;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\ProductVariant;
use Modules\Inventory\Models\ProductVariantUnit;
use Modules\Store\Models\CustomerAddress;
use Modules\Store\Models\StoreCheckoutQuote;
use Modules\Legal\Models\LegalDocument;

class StoreQuoteService
{
    public function __construct(private StoreSettingsService $settings,private SellerPolicyService $policies,
        private StoreShippingQuoteService $shipping,private SellerFinanceService $finance) {}
    public function address(array $data,?User $customer): array {
        $id=$data['shipping_address_id']??null;
        if($id) {
            if(!$customer) throw ValidationException::withMessages(['shipping_address_id'=>'يجب تسجيل الدخول لاستخدام عنوان محفوظ.']);
            $address=CustomerAddress::where('user_id',$customer->id)->findOrFail($id)->only([
                'recipient_name','phone','country','city','governorate_code','district','street','building','floor','apartment','landmark']);
        } else $address=$data['guest_address']??[];
        $address['country']='EG';
        $code=$address['governorate_code']??array_search($address['city']??'',StoreGeography::governorates(),true);
        if(!$code||!array_key_exists($code,StoreGeography::governorates()))
            throw ValidationException::withMessages(['guest_address.governorate_code'=>'اختر محافظة الشحن.']);
        $address['governorate_code']=$code;
        if(empty($address['recipient_name'])||empty($address['phone'])||empty($address['street']))
            throw ValidationException::withMessages(['shipping_address_id'=>'أكمل اسم المستلم والهاتف والعنوان.']);
        ksort($address);
        return $address;
    }
    public function documents(): array {
        $ids=$this->settings->get()['legal_document_ids'];
        $documents=LegalDocument::withoutGlobalScopes()->whereNull('deleted_at')->where('is_active',true)
            ->whereIn('id',$ids)->where(fn($q)=>$q->whereNull('company_id')->orWhere('company_id',config('app.master_company_id',1)))
            ->with(['versions'=>fn($q)=>$q->withoutGlobalScopes()->whereNull('deleted_at')->where('status','published')
                ->where('published_at','<=',now())->latest('id')])->get();
        $result=[];
        foreach($documents as $document) {
            $version=$document->versions->first();
            if($version) $result[]=['document_id'=>$document->id,'key'=>$document->key,'version_id'=>$version->id,
                'version'=>$version->version,'title'=>$version->title,'content_hash'=>hash('sha256',$version->content)];
        }
        if(count($result)!==count(array_unique($ids))) return [];
        return $result;
    }
    public function fingerprint(array $data,?User $customer): string {
        $items=array_map(fn($item)=>['product_id'=>(int)$item['product_id'],'variant_id'=>(int)$item['variant_id'],'quantity'=>(int)$item['quantity']],$data['items']);
        usort($items,fn($a,$b)=>$a['variant_id']<=>$b['variant_id']);
        return hash('sha256',json_encode([
            'customer_id'=>$customer?->id,'items'=>$items,'address'=>$this->address($data,$customer),
            'payment_method'=>$data['payment_method'],'notes'=>$data['notes']??null,
        ],JSON_THROW_ON_ERROR));
    }
    public function create(array $data,?User $customer): StoreCheckoutQuote {
        app(StoreOperationalHealth::class)->assertAcceptingOrders();
        $settings=$this->settings->get();
        if (!$settings['allow_guest_checkout'] && !$customer) {
            throw ValidationException::withMessages(['account'=>'سجل الدخول أو أنشئ حسابًا لإتمام الشراء.']);
        }
        if ($customer && $settings['require_verified_email'] && (!$customer->email || !$customer->email_verified_at)) {
            throw ValidationException::withMessages(['email_verification'=>'وثّق بريدك الإلكتروني لإتمام الشراء وحماية حسابك.']);
        }
        if(!$settings['enabled']||!$settings['legal_setup_confirmed']||$settings['tax_mode']==='unconfigured')
            throw ValidationException::withMessages(['store'=>'المتجر غير جاهز لاستقبال الطلبات حاليًا.']);
        if(!($settings[$data['payment_method']==='cod'?'cod_enabled':'online_enabled']))
            throw ValidationException::withMessages(['payment_method'=>'طريقة الدفع غير متاحة حاليًا.']);
        $legal=$this->documents();
        if(!$legal) throw ValidationException::withMessages(['legal'=>'شروط المتجر المنشورة لم تكتمل.']);
        $address=$this->address($data,$customer);
        $items=[]; $seen=[];
        foreach($data['items'] as $requested) {
            $id=(int)$requested['variant_id'];
            if(isset($seen[$id])) throw ValidationException::withMessages(['items'=>'لا تكرر نفس خيار المنتج في الطلب.']);
            $seen[$id]=true;
            $variant=ProductVariant::withoutGlobalScopes()->with([
                'product'=>fn($q)=>$q->withoutGlobalScopes(),'images',
            ])->where('status','active')->findOrFail($id);
            $product=$variant->product;
            if(!$product||$product->deleted_at||!$product->active||!$product->is_active_in_store
                ||(int)$product->id!==(int)$requested['product_id']||(int)$variant->company_id!==(int)$product->company_id
                ||!$this->policies->canPublish((int)$product->company_id))
                throw ValidationException::withMessages(['items'=>'أحد المنتجات لم يعد متاحًا للشراء.']);
            $base=$variant->base_unit_id?:$product->base_unit_id;
            $unit=$variant->display_unit_id?:($product->display_unit_id?:$base);
            if(!$base||!$unit) throw ValidationException::withMessages(['items'=>'وحدة بيع أحد المنتجات غير مكتملة.']);
            $factor='1.000000';
            if((int)$unit!==(int)$base) {
                $relation=ProductVariantUnit::withoutGlobalScopes()->where('product_variant_id',$id)->where('unit_id',$unit)->first();
                if(!$relation) throw ValidationException::withMessages(['items'=>'تحويل وحدة بيع أحد المنتجات غير مكتمل.']);
                $factor=(string)$relation->getRawOriginal('conversion_factor_to_base');
                if(!is_numeric($factor)||bccomp($factor,'0',6)<=0)
                    throw ValidationException::withMessages(['items'=>'تحويل وحدة البيع غير صالح.']);
            }
            $quantity=(int)$requested['quantity'];
            $unitMinor=Money::minor((string)$variant->retail_price);
            if($unitMinor<=0) throw ValidationException::withMessages(['items'=>'سعر أحد المنتجات غير صالح.']);
            $line=Money::minor(bcmul((string)$unitMinor,(string)$quantity,0),0);
            $rate=(int)$settings['default_tax_rate_bps'];
            $tax=$settings['tax_mode']==='exclusive'
                ?(int)bcdiv(bcadd(bcmul((string)$line,(string)$rate,0),'5000',0),'10000',0)
                :($settings['tax_mode']==='included'?$line-(int)bcdiv(bcadd(bcmul((string)$line,'10000',0),(string)intdiv(10000+$rate,2),0),(string)(10000+$rate),0):0);
            $gross=$settings['tax_mode']==='exclusive'?Money::minor(bcadd((string)$line,(string)$tax,0),0):$line;
            $returnPolicy=array_replace($settings['return_policy'],$product->store_return_policy??[]);
            if($returnPolicy['window_days']===null)
                throw ValidationException::withMessages(['items'=>'سياسة مرتجع أحد المنتجات لم تكتمل.']);
            $items[]=['product_id'=>$product->id,'variant_id'=>$id,'company_id'=>$product->company_id,
                'product_name'=>$product->name,'sku'=>$variant->sku,'image'=>$variant->primary_image_url,
                'quantity'=>$quantity,'unit_price_minor'=>$unitMinor,'total_minor'=>$gross,'tax_minor'=>$tax,
                'subtotal_minor'=>$gross-$tax,'selling_unit_id'=>(int)$unit,'base_unit_id'=>(int)$base,
                'unit_name'=>\Modules\Inventory\Models\Unit::withoutGlobalScopes()->find($unit)?->name,
                'conversion_factor'=>$factor,'base_quantity'=>bcmul((string)$quantity,$factor,6),'return_policy'=>$returnPolicy];
        }
        usort($items,fn($a,$b)=>[$a['company_id'],$a['variant_id']]<=>[$b['company_id'],$b['variant_id']]);
        $groups=[]; $subtotal=$tax=$shipping=$total=0;
        foreach(collect($items)->groupBy('company_id')->sortKeys() as $companyId=>$lines) {
            $sum=fn($key)=>Money::minor($lines->reduce(fn($carry,$line)=>bcadd($carry,(string)$line[$key],0),'0'),0);
            $groupSubtotal=$sum('subtotal_minor'); $groupTax=$sum('tax_minor'); $groupGross=$sum('total_minor');
            $shippingQuote=$this->shipping->quote((int)$companyId,$address['governorate_code'],$data['payment_method'],$groupGross);
            $policy=$this->policies->resolve((int)$companyId);
            $commission=$this->finance->commission($groupSubtotal,$policy);
            $shippingFinancials=ShippingFinancials::snapshot($shippingQuote,$groupGross,$commission);
            $net=$shippingFinancials['seller_net_minor'];
            if($net<0) throw ValidationException::withMessages(['shipping'=>'تكلفة الشحن المدعومة تتجاوز صافي مستحق البائع. راجع التسعير.']);
            $groups[]=['company_id'=>(int)$companyId,'items'=>$lines->values()->all(),
                'subtotal_minor'=>$groupSubtotal,'tax_minor'=>$groupTax,'shipping'=>$shippingQuote,
                'commission_minor'=>$commission,'seller_net_minor'=>$net,'shipping_financials'=>$shippingFinancials,'policy'=>$policy,'policy_hash'=>$this->policies->hash($policy)];
            $subtotal=Money::minor(bcadd((string)$subtotal,(string)$groupSubtotal,0),0);
            $tax=Money::minor(bcadd((string)$tax,(string)$groupTax,0),0);
            $shipping=Money::minor(bcadd((string)$shipping,(string)$shippingQuote['fee_minor'],0),0);
            $total=Money::minor(bcadd(bcadd((string)$subtotal,(string)$tax,0),(string)$shipping,0),0);
        }
        $snapshot=['groups'=>$groups,'address'=>$address,'shipping_address_id'=>$data['shipping_address_id']??null,
            'payment_method'=>$data['payment_method'],'notes'=>$data['notes']??null,'currency'=>$settings['currency'],
            'subtotal_minor'=>$subtotal,'tax_minor'=>$tax,'shipping_minor'=>$shipping,'total_minor'=>$total,
            'legal'=>$legal,'hold_hours'=>(int)$settings['hold_hours'],'tax_mode'=>$settings['tax_mode']];
        return StoreCheckoutQuote::create(['id'=>(string)Str::uuid(),'checkout_key'=>$data['checkout_key'],
            'customer_user_id'=>$customer?->id,'request_fingerprint'=>$this->fingerprint($data,$customer),
            'snapshot'=>$snapshot,'expires_at'=>now('UTC')->addMinutes((int)$settings['quote_minutes'])]);
    }
}
