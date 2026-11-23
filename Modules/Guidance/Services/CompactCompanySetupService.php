<?php
namespace Modules\Guidance\Services;

use App\Models\{Company,User};
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\Rule;
use Modules\Guidance\Models\CompanySetupState;
use Modules\Inventory\Models\Warehouse;
use Modules\Store\Models\StoreSellerProfile;
use Modules\Store\Services\{LocalDeliveryService,StoreTaxService};

/** A compact presentation of setup; existing operational requirements remain authoritative. */
class CompactCompanySetupService extends CompanySetupService
{
    private const PAGES=['basics'=>'نشاطك ومكان البضاعة','preferences'=>'تفضيلات العمل','store_setup'=>'تجهيز المتجر','summary'=>'النتيجة'];
    private function page(string $key): string {
        return match($key){'uses','company','locations','basics'=>'basics','inventory','billing','preferences'=>'preferences','store','store_setup'=>'store_setup','review','summary'=>'summary',default=>'summary'};
    }
    public function compact(User $u): array {
        $result=parent::snapshot($u);$c=$this->company($u);
        $s=CompanySetupState::where('user_id',$u->id)->where('company_id',$c->id)->first();
        $reviewed=$s?->reviewed??[];$deferred=$s?->deferred??[];
        // Combined pages are acknowledged only when all of their previous forms were reviewed.
        foreach(['basics'=>['company','locations'],'preferences'=>['inventory','billing'],'store_setup'=>['store'],'summary'=>['review']] as $new=>$old){
            if(!array_diff($old,$reviewed))$reviewed[]=$new;
            if(array_intersect($old,$deferred))$deferred[]=$new;
        }
        $result['version']=2;
        $result['steps']=array_map(fn($k)=>['key'=>$k,'title'=>self::PAGES[$k],'can_edit'=>$result['can_manage']||$k==='summary'],array_keys(self::PAGES));
        $result['state']=array_replace($result['state'],['current_step'=>$this->page($s?->current_step??'basics'),'reviewed'=>array_values(array_intersect(array_keys(self::PAGES),$reviewed)),'deferred'=>array_values(array_intersect(array_keys(self::PAGES),$deferred))]);
        foreach($result['issues'] as &$issue){$issue['section']=$issue['step'];$issue['step']=$this->page($issue['step']);}unset($issue);
        if(in_array('store',$result['data']['uses'])&&empty($result['data']['store']['profile']['pickup_address']['recipient_name']))$result['issues'][]=['step'=>'store_setup','section'=>'store','group'=>'store','message'=>'أكمل اسم مسؤول تسليم الشحنات من بيانات البائع.'];
        $result['store_ready']=in_array('store',$result['data']['uses'])&&!collect($result['issues'])->contains(fn($i)=>$i['group']==='store');
        $result['product_ready']=$result['basic_ready']||(bool)(count($result['data']['units'])&&count($result['data']['warehouses'])&&!collect($result['issues'])->contains(fn($i)=>in_array($i['section'],['company','locations','inventory'])));
        $result['data']['company']['website']=$result['data']['company']['website']??null;
        $result['data']['default_cash_customer_id']=$result['can_manage']?$c->default_cash_customer_id:null;
        return $result;
    }
    public function saveCompact(User $u,string $step,array $data,string $revision,bool $defer=false): array {
        abort_unless(isset(self::PAGES[$step]),404);
        $company=$this->company($u);
        DB::transaction(function()use($u,$company,$step,$data,$revision,$defer){
            $c=Company::withoutGlobalScopes()->lockForUpdate()->findOrFail($company->id);
            abort_unless(hash_equals($this->revision($c),$revision),409,'تغيرت البيانات. حدّثها قبل الحفظ؛ تعديلاتك ما زالت موجودة.');
            abort_unless(!$defer||$step!=='summary',422);
            if(!$defer&&$step!=='summary'){
                if($this->manager($u))$this->writeCompact($u,$c,$step,$data);
                else abort_unless($data===[],403);
            }
            $s=CompanySetupState::firstOrCreate(['user_id'=>$u->id,'company_id'=>$c->id]);
            $s->reviewed=$defer?array_values(array_diff($s->reviewed??[],[$step])):array_values(array_unique([...($s->reviewed??[]),$step]));
            $s->deferred=$defer?array_values(array_unique([...($s->deferred??[]),$step])):array_values(array_diff($s->deferred??[],[$step]));
            $keys=array_keys(self::PAGES);$s->current_step=$keys[min(array_search($step,$keys)+1,count($keys)-1)];
            $s->finished_at=$step==='summary'&&(!collect($this->issues($c->fresh(),$u,$this->uses($c->fresh())))->contains(fn($i)=>$i['group']==='basic')||!$this->manager($u))?now():null;
            $s->save();
        },3);
        return $this->compact($u);
    }
    public function option(User $u,string $section,array $data,string $revision): array {
        $company=$this->company($u);abort_unless($this->manager($u),403);
        DB::transaction(function()use($u,$company,$section,$data,$revision){
            $c=Company::withoutGlobalScopes()->lockForUpdate()->findOrFail($company->id);
            abort_unless(hash_equals($this->revision($c),$revision),409,'تغيرت البيانات. أعد تحميل النموذج قبل حفظه.');
            if($section==='company_extra'){
                $v=Validator::make($data,['website'=>'nullable|url|max:255'])->validate();$c->update($v);
            }elseif($section==='cash_customer'){
                $v=Validator::make($data,['create'=>'required|boolean'])->validate();
                if($v['create'])$c->getOrCreateDefaultCashCustomer();
            }elseif($section==='seller_identity')$this->identity($c,$data);
            elseif(in_array($section,['billing','locations']))$this->write($u,$c,$section,$data);
            else abort(404);
        },3);
        return $this->compact($u);
    }
    private function writeCompact(User $u,Company $c,string $step,array $data): void {
        if($step==='basics'){
            $v=Validator::make($data,['company'=>'required|array','warehouse'=>'sometimes|array'])->validate();
            $companyData=array_intersect_key($v['company'],array_flip(['name','phone','address','email','owner_name','logo_id','social_links']));
            $this->write($u,$c,'company',array_replace($c->only(['name','phone','address','email','owner_name']),$companyData));
            if(isset($v['warehouse'])){
                $w=Validator::make($v['warehouse'],['id'=>'required|integer','location'=>'required|string|max:255','latitude'=>'sometimes|nullable|required_with:longitude|numeric|between:-90,90','longitude'=>'sometimes|nullable|required_with:latitude|numeric|between:-180,180','location_confirmed'=>'sometimes|boolean'])->validate();
                $warehouse=Warehouse::withoutGlobalScopes()->where('company_id',$c->id)->where('status','active')->findOrFail($w['id']);
                if(($w['location_confirmed']??false)&&(!isset($w['latitude'])||!isset($w['longitude'])))throw \Illuminate\Validation\ValidationException::withMessages(['warehouse'=>'حدد نقطة المخزن قبل تأكيدها.']);
                $changedPoint=array_key_exists('latitude',$w)||array_key_exists('longitude',$w);
                if($changedPoint)$w['location_confirmed']=$w['location_confirmed']??false;
                unset($w['id']);$warehouse->update($w);
                Warehouse::withoutGlobalScopes()->where('company_id',$c->id)->update(['is_default'=>false]);$warehouse->update(['is_default'=>true]);
            }
        }elseif($step==='preferences'){
            $v=Validator::make($data,['auto_update_purchase_price'=>'sometimes|boolean','print_format'=>'sometimes|in:standard,a5,thermal,thermal_58','show_logo'=>'sometimes|boolean','footer_text'=>'sometimes|nullable|string|max:500'])->validate();
            $settings=$c->settings??[];
            if(array_key_exists('auto_update_purchase_price',$v))$settings['auto_update_purchase_price']=$v['auto_update_purchase_price'];
            $settings['print_settings']=array_replace($settings['print_settings']??[],array_intersect_key($v,array_flip(['print_format','show_logo','footer_text'])));$c->update(['settings'=>$settings]);
        }elseif($step==='store_setup'){
            $v=Validator::make($data,['warehouse_id'=>'sometimes|required|integer','phone'=>'sometimes|required|string|max:30','city'=>'sometimes|required|string|max:100','governorate_code'=>['sometimes','required',Rule::in(array_keys(\Modules\Store\Services\StoreGeography::governorates()))],'local_enabled'=>'sometimes|boolean','radius_km'=>'nullable|numeric|min:0.001|max:20000','fee'=>'sometimes|required|decimal:0,2|min:0|max:9999999999.99','free_above'=>'nullable|decimal:0,2|min:0|max:9999999999.99','payout_type'=>'nullable|in:bank,e_wallet','payout_account_holder'=>'nullable|required_with:payout_type|string|max:150','payout_account_number'=>'nullable|required_with:payout_type|string|max:80','payout_iban'=>'nullable|string|max:80'])->validate();
            $p=StoreSellerProfile::firstOrCreate(['company_id'=>$c->id],['is_enabled'=>false,'pickup_address'=>[]]);
            $pickup=$p->pickup_address??[];
            if(isset($v['warehouse_id'])){$w=Warehouse::withoutGlobalScopes()->where('company_id',$c->id)->where('status','active')->findOrFail($v['warehouse_id']);$p->warehouse_id=$w->id;$pickup['street']=$w->location;}
            foreach(['phone','city','governorate_code'] as $key)if(array_key_exists($key,$v))$pickup[$key]=$v[$key];
            $p->pickup_address=$pickup;
            if(!empty($v['payout_type']))$p->payout_destination_proposal=['type'=>$v['payout_type'],'account_holder'=>$v['payout_account_holder'],'account_number'=>$v['payout_account_number'],'iban'=>$v['payout_iban']??null];
            $p->save();
            if(array_key_exists('local_enabled',$v)){
                $old=app(LocalDeliveryService::class)->settings($c->id)['settings'];
                $radius=$v['radius_km']??($old['radius_meters']?($old['radius_meters']/1000):null);
                if($v['local_enabled']&&!$radius)throw \Illuminate\Validation\ValidationException::withMessages(['radius_km'=>'حدد نطاق التوصيل.']);
                app(LocalDeliveryService::class)->save($c->id,['enabled'=>$v['local_enabled'],'radius_km'=>$radius,'fee'=>$v['fee']??($old['fee_minor']/100),'free_above'=>array_key_exists('free_above',$v)?$v['free_above']:($old['free_above_minor']===null?null:$old['free_above_minor']/100)]);
            }
        }
    }
    private function identity(Company $c,array $data): void {
        $v=Validator::make($data,['recipient_name'=>'required|string|max:150','seller_kind'=>'required|in:individual,unregistered_business,registered_business','tax_status'=>'required|in:not_registered,registered,exempt','tax_mode'=>'required|in:none,included,exclusive','rate_bps'=>'required|integer|between:0,10000','registration_number'=>'nullable|string|max:100','basis'=>'required|string|max:1000'])->validate();
        if($v['tax_status']==='registered'&&(!$v['registration_number']||$v['tax_mode']==='none'))throw \Illuminate\Validation\ValidationException::withMessages(['tax_status'=>'أكمل بيانات التسجيل وطريقة حساب الضريبة.']);
        if($v['tax_status']!=='registered'&&($v['tax_mode']!=='none'||$v['rate_bps']!=0))throw \Illuminate\Validation\ValidationException::withMessages(['tax_status'=>'لا تضف ضريبة بيع لحالة غير مسجل أو معفى.']);
        $p=StoreSellerProfile::firstOrCreate(['company_id'=>$c->id],['is_enabled'=>false,'pickup_address'=>[]]);
        $old=$p->tax_profile??[];$business=$p->business_profile??[];$changed=($business['seller_kind']??null)!==$v['seller_kind']||($old['status']??null)!==$v['tax_status'];
        $p->update(['pickup_address'=>array_replace($p->pickup_address??[],['recipient_name'=>$v['recipient_name']]),'business_profile'=>array_replace($business,['seller_kind'=>$v['seller_kind'],'document_status'=>$changed?'self_declared':($business['document_status']??'self_declared'),'reference'=>$changed?null:($business['reference']??null)]),'tax_profile'=>array_replace(app(StoreTaxService::class)->defaults(),$old,['status'=>$v['tax_status'],'mode'=>$v['tax_mode'],'rate_bps'=>$v['rate_bps'],'registration_number'=>$v['registration_number']??null,'basis'=>$v['basis']]),'policy_review_required'=>$p->policy_review_required||($p->is_enabled&&$changed)]);
    }
}
