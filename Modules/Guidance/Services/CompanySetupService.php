<?php
namespace Modules\Guidance\Services;

use App\Models\{Company,User,PaymentMethod,InvoiceType,CashBox};
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\{Rule,ValidationException};
use Modules\Companies\Models\Branch;
use Modules\Inventory\Models\{Warehouse,Unit,UnitGroup};
use Modules\Guidance\Models\CompanySetupState;
use Modules\Store\Models\StoreSellerProfile;
use Modules\Store\Services\{LocalDeliveryService,SellerPolicyService,StoreTaxService,StoreSettingsService,StoreOperationalHealth};

/** Company data and user acknowledgements deliberately remain separate. */
class CompanySetupService {
    private const FEATURES=['inventory','sales','purchases','team','email','whatsapp','automation','store'];
    private const TITLES=['uses'=>'كيف ستستخدم شركتك؟','company'=>'بيانات شركتك','locations'=>'مكان عملك ومخزنك','inventory'=>'منتجاتك ومخزونك','billing'=>'فواتيرك واستلام الأموال','team'=>'فريق عملك','messages'=>'رسائلك وتنبيهاتك','store'=>'البيع في المتجر','review'=>'مراجعة الإعدادات'];
    public function company(User $user): Company {
        $id=(int)$user->active_company_id;
        abort_unless($id&&$user->companies()->where('companies.id',$id)->wherePivot('status','active')->exists(),403);
        return Company::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($id);
    }
    private function manager(User $u): bool {return $u->checkPermissionTo('admin.super')||$u->checkPermissionTo('admin.company')||$u->checkPermissionTo('guidance.setup.manage');}
    private function uses(Company $c): array {
        if(isset($c->settings['setup_uses']))return array_values(array_intersect(self::FEATURES,$c->settings['setup_uses']));
        $uses=['inventory','sales'];
        if(StoreSellerProfile::where('company_id',$c->id)->exists())$uses[]='store';
        if($this->staff($c)->count()>1)$uses[]='team';
        if(DB::table('invoices as i')->join('invoice_types as t','t.id','=','i.invoice_type_id')->where('i.company_id',$c->id)->whereIn('t.code',['purchase','purchases'])->exists())$uses[]='purchases';
        foreach(['email'=>\Modules\Notification\Models\MailSetting::class,'whatsapp'=>\Modules\Notification\Models\WhatsAppSetting::class] as $feature=>$model)if($model::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id',$c->id)->where('is_active',true)->exists())$uses[]=$feature;
        if(\Modules\Notification\Models\NotificationWorkflow::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id',$c->id)->where('is_active',true)->exists())$uses[]='automation';
        return $uses;
    }
    private function steps(array $uses): array {
        $keys=['uses','company','locations'];
        if(in_array('inventory',$uses))$keys[]='inventory';
        if(array_intersect(['sales','purchases'],$uses))$keys[]='billing';
        if(in_array('team',$uses))$keys[]='team';
        if(array_intersect(['email','whatsapp','automation'],$uses))$keys[]='messages';
        if(in_array('store',$uses))$keys[]='store';
        return [...$keys,'review'];
    }
    private function rows(string $model,int $company,array $fields): array {return $model::withoutGlobalScopes()->where('company_id',$company)->get($fields)->toArray();}
    private function data(Company $c,User $u): array {
        $manage=$this->manager($u);
        $branches=Branch::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id',$c->id)->get(['id','name','address','phone','is_active','is_default'])->toArray();
        $warehouses=$this->rows(Warehouse::class,$c->id,['id','name','branch_id','location','latitude','longitude','location_confirmed','is_default','status']);
        $units=Unit::withoutGlobalScopes()->where(fn($q)=>$q->where('company_id',$c->id)->orWhereNull('company_id'))->where('is_active',true)->get(['id','name','code','unit_group_id','decimal_places','company_id'])->toArray();
        $methods=PaymentMethod::withoutGlobalScopes()->where('company_id',$c->id)->where('active',true)->get(['id','name','code'])->toArray();
        $types=InvoiceType::where(fn($q)=>$q->whereNull('company_id')->orWhere('company_id',$c->id)->orWhereHas('companies',fn($assigned)=>$assigned->where('companies.id',$c->id)))->get(['id','name','code'])->toArray();
        $activeTypes=DB::table('company_invoice_type')->where('company_id',$c->id)->where('is_active',true)->pluck('invoice_type_id')->all();
        $boxes=CashBox::withoutGlobalScopes()->where('company_id',$c->id)->where('status','active')->get()->filter(fn($box)=>$u->canAccessCashBox($box))->map(fn($box)=>$box->only(['id','name','branch_id','is_default']))->values()->all();
        $profile=StoreSellerProfile::where('company_id',$c->id)->first();
        return ['company'=>array_replace($c->only(['id','name','address','phone','email','owner_name','website','social_links']),['logo_url'=>$c->logo?->url]),
            'uses'=>$this->uses($c),'branches'=>$manage?$branches:array_values(array_filter($branches,fn($b)=>$u->branches()->where('branches.id',$b['id'])->exists())), 'warehouses'=>$manage?$warehouses:array_values(array_filter($warehouses,fn($w)=>$u->branches()->where('branches.id',$w['branch_id'])->exists())),
            'units'=>$units,'payment_methods'=>$manage?$methods:[],'invoice_types'=>$manage?$types:[], 'active_invoice_type_ids'=>$manage?array_values(array_intersect($activeTypes,array_column($types,'id'))):[],
            'cash_boxes'=>$manage?$boxes:[],'settings'=>$manage?array_intersect_key($c->settings??[],array_flip(['setup_unit_id','setup_payment_method_id','inventory_valuation_method','auto_update_purchase_price','print_settings'])):[],
            'store'=>$manage?['profile'=>$profile?->only(['warehouse_id','pickup_address','business_profile','tax_profile','is_enabled','policy_review_required']),
                'delivery'=>app(LocalDeliveryService::class)->settings($c->id),'can_publish'=>app(SellerPolicyService::class)->canPublish($c->id),'payout_destination_configured'=>(bool)$profile?->payout_destination,'payout_proposal'=>$profile?->payout_destination_proposal,'return_policy'=>app(StoreSettingsService::class)->get()['return_policy'],
                'policy'=>app(SellerPolicyService::class)->resolve($c->id)]:null];
    }
    private function revision(Company $c): string {
        $parts=[$c->getAttributes()];
        foreach(['branches','warehouses','payment_methods','units','company_invoice_type','store_seller_profiles'] as $table)
            $parts[]=DB::table($table)->where('company_id',$c->id)->orderBy($table==='company_invoice_type'?'invoice_type_id':'id')->get()->toArray();
        // Only an opaque digest leaves the server; encrypted account destinations never leave it.
        return hash('sha256',serialize($parts));
    }
    public function snapshot(User $u): array {
        $c=$this->company($u);$state=CompanySetupState::where('user_id',$u->id)->where('company_id',$c->id)->first();$uses=$this->uses($c);
        $issues=$this->issues($c,$u,$uses);$keys=$this->steps($uses);$manager=$this->manager($u);
        return ['company_id'=>$c->id,'revision'=>$this->revision($c),'can_manage'=>$manager,'steps'=>array_map(fn($k)=>['key'=>$k,'title'=>self::TITLES[$k],'can_edit'=>$manager||in_array($k,['review','team','messages'])],$keys),
            'state'=>['current_step'=>in_array($state?->current_step,$keys)?$state->current_step:'uses','reviewed'=>$state?->reviewed??[], 'deferred'=>$state?->deferred??[], 'hidden'=>$state?->hidden??false,'finished'=>!!$state?->finished_at],
            'data'=>$this->data($c,$u),'issues'=>$issues,'ready'=>!$issues,'basic_ready'=>!array_filter($issues,fn($i)=>$i['group']==='basic')];
    }
    private function issues(Company $c,User $u,array $uses): array {
        $issues=[];$add=function($step,$message,$group='basic')use(&$issues){$issues[]=['step'=>$step,'message'=>$message,'group'=>$group];};
        if((int)$c->id!==(int)config('app.master_company_id',1)&&!$u->checkPermissionTo('admin.super')){
            $subscription=\App\Services\SaaS\SaaSEngine::getActiveSubscription($c->id);
            if(!$subscription)$add('uses','اشتراك الشركة غير نشط. راجع اشتراكك قبل بدء العمل.');
            else foreach(['inventory'=>'products','sales'=>'invoices','purchases'=>'invoices'] as $feature=>$resource)if(in_array($feature,$uses)&&!\App\Services\SaaS\LimitResolver::isWithinLimit($c->id,$resource))$add('uses','حد الاستخدام المتاح في باقتك لا يسمح ببدء عملية جديدة؛ راجع اشتراكك.');
        }
        if(!$c->name||!$c->phone||!$c->address)$add('company','أكمل اسم الشركة ورقم التواصل والعنوان.');
        $branch=Branch::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id',$c->id)->where('is_active',true)->where('is_default',true)->first();
        if(!$branch)$add('locations','حدد فرعًا رئيسيًا نشطًا.');
        $warehouse=Warehouse::withoutGlobalScopes()->where('company_id',$c->id)->where('is_default',true)->where('status','active')->first();
        if(!$warehouse||!$warehouse->location||!$branch||$warehouse->branch_id!=$branch->id)$add('locations','أكمل عنوان المخزن الرئيسي واربطه بالفرع الرئيسي.');
        if(in_array('inventory',$uses)&&!Unit::withoutGlobalScopes()->where(fn($q)=>$q->where('company_id',$c->id)->orWhereNull('company_id'))->where('is_active',true)->exists())$add('inventory','أضف وحدة بيع واحدة على الأقل.');
        if(array_intersect(['sales','purchases'],$uses)){
            $codes=InvoiceType::join('company_invoice_type as p','p.invoice_type_id','=','invoice_types.id')->where('p.company_id',$c->id)->where('p.is_active',true)->pluck('invoice_types.code')->all();
            if(in_array('sales',$uses)&&!array_intersect(['sale','sales','sales_invoice'],$codes))$add('billing','فعّل نوع فاتورة المبيعات.');
            if(in_array('purchases',$uses)&&!array_intersect(['purchase','purchases'],$codes))$add('billing','فعّل نوع فاتورة المشتريات.');
            if(!PaymentMethod::withoutGlobalScopes()->where('company_id',$c->id)->where('active',true)->exists())$add('billing','أضف وسيلة لاستلام الأموال.');
            if(!CashBox::withoutGlobalScopes()->where('company_id',$c->id)->where('status','active')->get()->contains(fn($box)=>$u->canAccessCashBox($box)))$add('billing','جهّز خزنة نشطة مسموحة لك لاستلام الأموال.');
            if(!$c->default_cash_customer_id||!$c->users()->where('users.id',$c->default_cash_customer_id)->wherePivot('status','active')->exists())$add('billing','جهّز العميل النقدي للفواتير التي لا تحتاج عميلًا مسجلًا.');
        }
        if(in_array('team',$uses)&&$this->staff($c)->count()<2)$add('team','أضف موظفًا وحدد دوره وأماكن عمله، أو اختر العمل منفردًا.','team');
        if(in_array('team',$uses))foreach($this->staff($c) as $member){
            if($member->getAllPermissions()->isEmpty())$add('team','حدد دورًا وصلاحيات للموظفين الذين أضفتهم.','team');
            if(!$member->branches()->where('branches.company_id',$c->id)->where('is_active',true)->exists())$add('team','اربط كل موظف بفرع العمل المسموح له.','team');
        }
        $resolver=app(\Modules\Notification\Services\NotificationIntegrationResolver::class);
        if(in_array('email',$uses)&&!SetupConnectionCheck::verified('email',$resolver->mail($c->id)))$add('messages','أضف وسيلة بريد مفعلة واختبر إرسال رسالة منها.','messages');
        if(in_array('whatsapp',$uses)&&!SetupConnectionCheck::verified('whatsapp',$resolver->whatsapp($c->id)))$add('messages','أكمل حساب واتساب واختبر إرسال رسالة منه.','messages');
        if(in_array('automation',$uses)&&!$this->automationReady($c))$add('messages','أكمل تنبيهًا فعالًا بقالب وخطوة إرسال وقناة مجربة متاحة لشركتك.','messages');
        if(in_array('store',$uses)){
            $p=StoreSellerProfile::where('company_id',$c->id)->first();
            if(!$p||!$p->warehouse_id||empty($p->pickup_address['street'])||empty($p->pickup_address['phone']))$add('store','أكمل بيانات البائع ومخزن تجهيز الطلبات وعنوان الاستلام.','store');
            if(!$p?->is_enabled||$p?->policy_review_required)$add('store','يلزم اعتماد إعداد البائع من إدارة المنصة.','store');
            if(!app(SellerPolicyService::class)->canPublish($c->id))$add('store','باقتك الحالية لا تتيح نشر المنتجات في المتجر.','store');
            try{app(StoreTaxService::class)->resolve($c->id,0);}catch(ValidationException $e){$add('store','أكمل بيانات ضريبة البيع بحسب حالتك.','store');}
            if(!$p||!app(LocalDeliveryService::class)->available($p)){
                $hasCarrier=$p&&app(\Modules\Store\Services\StoreReadinessService::class)->hasShippingRoute($p,app(StoreSettingsService::class)->get());
                if(!$hasCarrier)$add('store','جهّز التوصيل الذاتي وحدد نقطة المخزن ورسم التوصيل.','store');
            }
            $platform=app(StoreSettingsService::class)->get();
            if(app(\Modules\Store\Services\StorePaymentService::class)->methods($platform)['online_enabled']&&!$p?->payout_destination)$add('store',$p?->payout_destination_proposal?'بيانات استلام مستحقاتك بانتظار اعتماد إدارة المنصة.':'أدخل بيانات استلام مستحقاتك من المنصة لاعتمادها.','store');
            if(!$platform['enabled'])$add('store','المتجر العام لا يستقبل طلبات حاليًا؛ فتحه من اختصاص إدارة المنصة.','store');
            if(!$platform['legal_setup_confirmed']||$platform['tax_mode']==='unconfigured'||!app(\Modules\Store\Services\StoreQuoteService::class)->documents())$add('store','إدارة المنصة لم تستكمل إعداد سياسات البيع بعد.','store');
            if(app(StoreOperationalHealth::class)->issues())$add('store','تحتاج خدمات تشغيل المتجر مراجعة من إدارة المنصة.','store');
        }
        return $issues;
    }
    public function save(User $u,string $step,array $data,string $revision,bool $defer=false): array {
        $company=$this->company($u);
        DB::transaction(function()use($u,$company,$step,$data,$revision,$defer){
            $c=Company::withoutGlobalScopes()->lockForUpdate()->findOrFail($company->id);
            abort_unless(hash_equals($this->revision($c),$revision),409,'تغيرت بعض البيانات منذ فتحها. حدّث البيانات وراجعها قبل الحفظ.');
            $keys=$this->steps($this->uses($c));abort_unless(in_array($step,$keys),404);
            $s=CompanySetupState::firstOrCreate(['user_id'=>$u->id,'company_id'=>$c->id]);
            if($defer){abort_unless(in_array($step,['team','messages','store']),422);}
            elseif(!in_array($step,['team','messages','review'])){
                if($this->manager($u))$this->write($u,$c,$step,$data);else abort_unless($data===[],403);
            }
            $s->reviewed=array_values(array_unique([...($s->reviewed??[]),$step]));
            $s->deferred=$defer?array_values(array_unique([...($s->deferred??[]),$step])):array_values(array_diff($s->deferred??[],[$step]));
            $keys=$this->steps($this->uses($c->fresh()));$index=array_search($step,$keys);
            $s->current_step=$keys[min(($index===false?0:$index)+1,count($keys)-1)];
            if($step==='review'){
                $basicMissing=array_filter($this->issues($c->fresh(),$u,$this->uses($c->fresh())),fn($i)=>$i['group']==='basic');
                $s->finished_at=(!$basicMissing||!$this->manager($u))?now():null;
            }else $s->finished_at=null;
            $s->save();
        },3);
        return $this->snapshot($u);
    }
    public function preferences(User $u,bool $hidden): array {
        $c=$this->company($u);CompanySetupState::updateOrCreate(['user_id'=>$u->id,'company_id'=>$c->id],['hidden'=>$hidden]);return $this->snapshot($u);
    }
    private function write(User $u,Company $c,string $step,array $input): void {
        $validate=fn($rules)=>Validator::make($input,$rules)->validate();
        if($step==='uses'){
            $v=$validate(['uses'=>'required|array|min:1','uses.*'=>['required',Rule::in(self::FEATURES)]]);
            if(in_array('automation',$v['uses'])&&!array_intersect(['email','whatsapp'],$v['uses']))throw ValidationException::withMessages(['uses'=>'اختر البريد أو واتساب لإرسال التنبيهات التلقائية.']);
            $settings=$c->settings??[];$settings['setup_uses']=array_values(array_unique($v['uses']));$c->update(['settings'=>$settings]);return;
        }
        if($step==='company'){
            $v=$validate(['name'=>'required|string|max:255','phone'=>'required|string|max:15','address'=>'required|string|max:255','email'=>['nullable','email',Rule::unique('companies','email')->ignore($c->id)],'owner_name'=>'nullable|string|max:255','website'=>'nullable|url|max:255','social_links'=>'nullable|array','social_links.*.platform'=>'required|string|max:50','social_links.*.url'=>['nullable','url','regex:~^https?://~i']]);if(!empty($input['logo_id'])){
                $image=\App\Models\Image::withoutGlobalScopes()->where('company_id',$c->id)->where(fn($q)=>$q->where('is_temp',true)->orWhereNull('imageable_id')->orWhere(fn($q)=>$q->where('imageable_type',$c->getMorphClass())->where('imageable_id',$c->id)))->findOrFail((int)$input['logo_id']);
                // Keep the previous asset for rollback/history rather than deleting files inside a transaction.
                \App\Models\Image::where('imageable_type',$c->getMorphClass())->where('imageable_id',$c->id)->where('type','logo')->update(['type'=>'gallery','is_primary'=>false]);
                $image->update(['imageable_type'=>$c->getMorphClass(),'imageable_id'=>$c->id,'type'=>'logo','is_primary'=>true,'is_temp'=>false]);
            }
            $c->update($v);return;
        }
        if($step==='locations'){
            $v=$validate(['branch_id'=>'nullable|integer','branch_name'=>'required|string|max:255','warehouse_id'=>'nullable|integer','name'=>'required|string|max:255','location'=>'required|string|max:255','latitude'=>'nullable|required_with:longitude|numeric|between:-90,90','longitude'=>'nullable|required_with:latitude|numeric|between:-180,180','location_confirmed'=>'required|boolean']);
            if($v['location_confirmed']&&(!isset($v['latitude'])||!isset($v['longitude'])))throw ValidationException::withMessages(['location'=>'حدد نقطة المخزن قبل تأكيدها.']);
            $b=!empty($v['branch_id'])?Branch::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id',$c->id)->findOrFail($v['branch_id']):Branch::withoutGlobalScopes()->where('company_id',$c->id)->where('is_default',true)->first();
            $b??=new Branch(['company_id'=>$c->id,'created_by'=>$u->id]);$b->fill(['name'=>$v['branch_name'],'address'=>$v['location'],'is_active'=>true,'is_default'=>true])->save();
            Branch::withoutGlobalScopes()->where('company_id',$c->id)->where('id','!=',$b->id)->update(['is_default'=>false]);
            $w=!empty($v['warehouse_id'])?Warehouse::withoutGlobalScopes()->where('company_id',$c->id)->findOrFail($v['warehouse_id']):Warehouse::withoutGlobalScopes()->where('company_id',$c->id)->where('is_default',true)->first();
            if($w&&$w->branch_id&&$w->branch_id!=$b->id)throw ValidationException::withMessages(['warehouse_id'=>'اختر مخزنًا تابعًا لهذا الفرع؛ لا ننقل المخزن المستخدم إلى فرع آخر.']);
            $w??=new Warehouse(['company_id'=>$c->id,'created_by'=>$u->id]);$w->fill(['name'=>$v['name'],'location'=>$v['location'],'branch_id'=>$b->id,'status'=>'active','is_default'=>true,'latitude'=>$v['latitude']??null,'longitude'=>$v['longitude']??null,'location_confirmed'=>$v['location_confirmed']])->save();
            Warehouse::withoutGlobalScopes()->where('company_id',$c->id)->where('id','!=',$w->id)->update(['is_default'=>false]);
            $u->branches()->syncWithoutDetaching([$b->id]);DB::table('branch_user')->where('user_id',$u->id)->where('branch_id',$b->id)->update(['default_warehouse_id'=>$w->id]);return;
        }
        if($step==='inventory'){
            $v=$validate(['unit_id'=>'nullable|integer','unit_name'=>'nullable|string|max:100','decimal_places'=>'required|integer|between:0,6','auto_update_purchase_price'=>'required|boolean']);
            if(!empty($v['unit_id']))$unit=Unit::withoutGlobalScopes()->where(fn($q)=>$q->where('company_id',$c->id)->orWhereNull('company_id'))->where('is_active',true)->findOrFail($v['unit_id']);
            else{
                if(empty(trim($v['unit_name']??'')))throw ValidationException::withMessages(['unit_name'=>'اختر وحدة موجودة أو اكتب اسم وحدة جديدة.']);
                $group=UnitGroup::firstOrCreate(['company_id'=>$c->id,'name'=>'وحدات البيع'],['type'=>'count','created_by'=>$u->id]);
                $unit=Unit::firstOrCreate(['company_id'=>$c->id,'name'=>$v['unit_name'],'unit_group_id'=>$group->id],['code'=>'U'.substr(sha1($c->id.':'.$v['unit_name']),0,12),'decimal_places'=>$v['decimal_places'],'is_active'=>true,'created_by'=>$u->id]);
            }
            $settings=$c->settings??[];$settings['setup_unit_id']=$unit->id;$settings['auto_update_purchase_price']=$v['auto_update_purchase_price'];$c->update(['settings'=>$settings]);return;
        }
        if($step==='billing'){
            $v=$validate(['invoice_type_ids'=>'present|array','create_invoice_types'=>'sometimes|boolean','invoice_type_ids.*'=>'integer','payment_method_id'=>'nullable|integer','payment_method_name'=>'nullable|string|max:100','create_cash_box'=>'required|boolean','create_cash_customer'=>'required|boolean','print_format'=>'required|in:standard,a5,thermal,thermal_58','show_logo'=>'required|boolean','footer_text'=>'nullable|string|max:500']);
            if(!empty($v['create_invoice_types'])){
                foreach(['sale'=>['فاتورة بيع','sales'],'purchase'=>['فاتورة شراء','purchases'],'sale_return'=>['مرتجع بيع','sales'],'purchase_return'=>['مرتجع شراء','purchases']] as $code=>$info){
                    $type=InvoiceType::firstOrCreate(['company_id'=>$c->id,'code'=>$code],['name'=>$info[0],'context'=>$info[1],'created_by'=>$u->id]);$v['invoice_type_ids'][]=$type->id;
                }
                $v['invoice_type_ids']=array_values(array_unique($v['invoice_type_ids']));
            }
            if(!$v['invoice_type_ids'])throw ValidationException::withMessages(['invoice_type_ids'=>'اختر نوع فاتورة أو جهز الأنواع الأساسية.']);
            $types=InvoiceType::where(fn($q)=>$q->whereNull('company_id')->orWhere('company_id',$c->id)->orWhereHas('companies',fn($assigned)=>$assigned->where('companies.id',$c->id)))->whereIn('id',$v['invoice_type_ids'])->pluck('id')->all();
            if(count($types)!==count(array_unique($v['invoice_type_ids'])))throw ValidationException::withMessages(['invoice_type_ids'=>'اختر أنواع فواتير متاحة لشركتك.']);
            // Preserve additional existing invoice types; the guide never disables working configuration.
            $c->invoiceTypes()->syncWithoutDetaching(array_fill_keys($types,['is_active'=>true]));
            if(!empty($v['payment_method_id']))$method=PaymentMethod::withoutGlobalScopes()->where('company_id',$c->id)->where('active',true)->findOrFail($v['payment_method_id']);
            else{
                if(empty(trim($v['payment_method_name']??'')))throw ValidationException::withMessages(['payment_method_name'=>'اختر وسيلة دفع أو اكتب اسم الوسيلة الجديدة.']);
                $method=PaymentMethod::firstOrCreate(['company_id'=>$c->id,'name'=>$v['payment_method_name']],['code'=>'P'.substr(sha1($c->id.':'.$v['payment_method_name']),0,12),'active'=>true,'is_system'=>false,'created_by'=>$u->id]);
            }
            if($v['create_cash_customer']){
                if($c->default_cash_customer_id&&!$c->users()->where('users.id',$c->default_cash_customer_id)->wherePivot('status','active')->exists())$c->update(['default_cash_customer_id'=>null]);
                $c->getOrCreateDefaultCashCustomer();
            }
            if($v['create_cash_box'])$this->ensureCashBox($u,$c);
            $settings=$c->fresh()->settings??[];$settings['setup_payment_method_id']=$method->id;$settings['print_settings']=array_replace($settings['print_settings']??[],array_intersect_key($v,array_flip(['print_format','show_logo','footer_text'])));$c->update(['settings'=>$settings]);return;
        }
        if($step==='store')$this->saveSeller($u,$c,$input);
    }
    private function automationReady(Company $c): bool {
        $resolver=app(\Modules\Notification\Services\NotificationIntegrationResolver::class);
        $valid=['email'=>SetupConnectionCheck::verified('email',$resolver->mail($c->id)),'whatsapp'=>SetupConnectionCheck::verified('whatsapp',$resolver->whatsapp($c->id))];
        return \Modules\Notification\Models\NotificationWorkflow::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id',$c->id)->where('is_active',true)->with(['steps.template'])->get()->contains(function($workflow)use($valid,$c){
            $steps=$workflow->steps->where('is_active',true);if($steps->isEmpty())return false;
            return $steps->every(function($step)use($valid,$c){
                $template=$step->template;if(!$template||!$template->is_active||(int)$template->company_id!==$c->id)return false;
                $channels=$step->channel;if(is_string($channels))$channels=$channels==='both'?['email','whatsapp']:[$channels];
                return !empty($channels)&&collect($channels)->every(fn($channel)=>!empty($valid[$channel]));
            });
        });
    }
    private function staff(Company $c): \Illuminate\Support\Collection {
        return User::withoutGlobalScopes()->whereIn('id',DB::table('company_user')->where('company_id',$c->id)->where('status','active')->select('user_id'))->get()->filter(fn($u)=>$u->checkPermissionTo('admin.company')||$u->hasCapability('is_internal',$c->id));
    }
    private function ensureCashBox(User $u,Company $c): void {
        $branch=Branch::withoutGlobalScopes()->where('company_id',$c->id)->where('is_default',true)->first();
        if(!$branch)throw ValidationException::withMessages(['create_cash_box'=>'جهّز الفرع الرئيسي أولًا.']);
        $existing=CashBox::withoutGlobalScopes()->where('company_id',$c->id)->where('branch_id',$branch->id)->where('status','active')->get()->first(fn($box)=>$u->canAccessCashBox($box));
        if(!$existing){
            $type=\App\Models\CashBoxType::firstOrCreate(['company_id'=>$c->id,'name'=>'نقدي'],['is_active'=>true,'is_system'=>false,'created_by'=>$u->id]);
            $existing=app(\App\Services\CashBoxLifecycleService::class)->create(['company_id'=>$c->id,'branch_id'=>$branch->id,'name'=>'خزنة الشركة','balance'=>0,'user_id'=>null,'cash_box_type_id'=>$type->id,'status'=>'active','access_type'=>'company_shared','created_by'=>$u->id],$u);
        }
        $u->branches()->syncWithoutDetaching([$branch->id]);
        app(\App\Services\CashBoxLifecycleService::class)->changeDefault($u,$existing->id,null,$branch->id);
    }
    private function saveSeller(User $u,Company $c,array $input): void {
        $v=Validator::make($input,['warehouse_id'=>'required|integer','recipient_name'=>'required|string|max:150','phone'=>'required|string|max:30','city'=>'required|string|max:100','governorate_code'=>['required',Rule::in(array_keys(\Modules\Store\Services\StoreGeography::governorates()))],
            'seller_kind'=>'required|in:individual,unregistered_business,registered_business','tax_status'=>'required|in:not_registered,registered,exempt','tax_mode'=>'required|in:none,included,exclusive','rate_bps'=>'required|integer|between:0,10000','registration_number'=>'nullable|string|max:100','basis'=>'required|string|max:1000',
            'payout_type'=>'nullable|in:bank,e_wallet','payout_account_holder'=>'nullable|required_with:payout_type|string|max:150','payout_account_number'=>'nullable|required_with:payout_type|string|max:80','payout_iban'=>'nullable|string|max:80','local_enabled'=>'required|boolean','radius_km'=>'nullable|numeric|min:0.001|max:20000','fee'=>'required|decimal:0,2|min:0|max:9999999999.99','free_above'=>'nullable|decimal:0,2|min:0|max:9999999999.99'])->validate();
        $warehouse=Warehouse::withoutGlobalScopes()->where('company_id',$c->id)->findOrFail($v['warehouse_id']);
        if(!$warehouse->location)throw ValidationException::withMessages(['warehouse_id'=>'أكمل عنوان المخزن أولًا.']);
        if($v['local_enabled']&&!$v['radius_km'])throw ValidationException::withMessages(['radius_km'=>'حدد نطاق التوصيل.']);
        if($v['tax_status']==='registered'&&(!$v['registration_number']||$v['tax_mode']==='none'))throw ValidationException::withMessages(['tax_status'=>'أكمل رقم التسجيل وطريقة احتساب ضريبة البيع.']);
        if($v['tax_status']!=='registered'&&($v['tax_mode']!=='none'||(int)$v['rate_bps']!==0))throw ValidationException::withMessages(['tax_status'=>'لا تضف ضريبة بيع عندما تكون حالتك غير مسجل أو معفى.']);
        $p=StoreSellerProfile::firstOrCreate(['company_id'=>$c->id],['is_enabled'=>false,'pickup_address'=>[]]);
        $previousTax=$p->tax_profile;
        if(!empty($v['payout_type']))$p->payout_destination_proposal=['type'=>$v['payout_type'],'account_holder'=>$v['payout_account_holder'],'account_number'=>$v['payout_account_number'],'iban'=>$v['payout_iban']??null];
        $tax=array_replace(app(StoreTaxService::class)->defaults(),$p->tax_profile??[],['status'=>$v['tax_status'],'mode'=>$v['tax_mode'],'rate_bps'=>$v['rate_bps'],'registration_number'=>$v['registration_number']??null,'basis'=>$v['basis']]);
        $reviewNeeded=$p->is_enabled&&(($p->business_profile['seller_kind']??null)!==$v['seller_kind']||($previousTax&&($previousTax['status']??null)!==$v['tax_status']));
        $p->update(['policy_review_required'=>$p->policy_review_required||$reviewNeeded,'warehouse_id'=>$warehouse->id,'pickup_address'=>array_replace($p->pickup_address??[],['recipient_name'=>$v['recipient_name'],'phone'=>$v['phone'],'street'=>$warehouse->location,'city'=>$v['city'],'governorate_code'=>$v['governorate_code']]),
            'business_profile'=>array_replace($p->business_profile??[],['seller_kind'=>$v['seller_kind'],'document_status'=>(($p->business_profile['seller_kind']??null)===$v['seller_kind']?($p->business_profile['document_status']??'self_declared'):'self_declared'),'reference'=>(($p->business_profile['seller_kind']??null)===$v['seller_kind']?($p->business_profile['reference']??null):null)]),'tax_profile'=>$tax]);
        app(LocalDeliveryService::class)->save($c->id,['enabled'=>$v['local_enabled'],'radius_km'=>$v['radius_km']??null,'fee'=>$v['fee'],'free_above'=>$v['free_above']??null]);
    }
}
