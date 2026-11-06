<?php
namespace Modules\Store\Http\Controllers;
use App\Models\{Company,Plan};
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Inventory\Models\Warehouse;
use Modules\Store\Models\{StoreOrder,StoreSubOrder,StoreSellerProfile,StoreShippingAccount,StoreShippingRate,StoreShipment,StoreOutbox,StoreSellerAccount};
use Modules\Store\Services\{StoreAccess,StoreSettingsService,StoreReadinessService,StoreGeography,StorePolicyRules,SellerPolicyService,StoreOrderService,StoreLedgerService,StoreShipmentService,StorePaymentService};
use Modules\Store\Transformers\StoreOrderResource;

class StoreAdminController extends Controller
{
    public function rto(Request $request,string $id) {
        $this->access->platform('store.orders.manage');$this->access->platform('store.finance.manage');
        $data=$request->validate(['confirmed'=>'required|accepted','condition'=>'required|in:saleable,damaged',
            'reason'=>'required|in:customer_refused,unreachable,courier_failed,seller_error,other',
            'carrier_reference'=>'required|string|max:150','carrier_cost'=>'required|decimal:0,2|min:0|max:9999999999.99']);
        $receipt=app(\Modules\Store\Services\StoreRtoService::class)->receive(StoreSubOrder::findOrFail($id),$data);
        $body=$this->order($request,(string)StoreSubOrder::findOrFail($id)->store_order_id)->getData(true);
        $body['data']['rto_receipt']=$receipt;
        return response()->json($body);
    }
    public function payments() {
        $this->access->platform('store.finance.manage');
        $payments=\Modules\Payment\Models\PaymentTransaction::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('payable_type',StoreOrder::class)->select(['id','payable_id','amount','currency','status','payment_gateway_id','gateway_driver','gateway_reference','gateway_payment_reference','created_at','verified_at'])->latest('updated_at')->paginate(25);
        $payments->getCollection()->transform(fn($payment)=>$payment->toArray()+['unsettled_minor'=>app(\Modules\Store\Services\StoreGatewaySettlementService::class)->remaining($payment)]);
        return response()->json(['data'=>$payments]);
    }
    public function gatewaySettlements() {
        $this->access->platform('store.finance.manage');
        return response()->json(['data'=>\Modules\Store\Models\StoreGatewaySettlement::latest('id')->paginate(20)]);
    }
    public function recordGatewaySettlement(Request $request) {
        $this->access->platform('store.finance.manage');
        $data=$request->validate(['payment_ids'=>'required|array|min:1|max:100','payment_ids.*'=>'required|integer|distinct|min:1',
            'net_amount'=>'required|decimal:0,2|min:-9999999999.99|max:9999999999.99','fee'=>'required|decimal:0,2|min:0|max:9999999999.99',
            'recoverable_tax'=>'nullable|decimal:0,2|min:0|max:9999999999.99','reference'=>'required|string|max:150','operation_key'=>'required|uuid','confirmed'=>'required|accepted']);
        return response()->json(['data'=>app(\Modules\Store\Services\StoreGatewaySettlementService::class)->record($data)],201);
    }
    public function reconcilePayment(Request $request,string $id) {
        $this->access->platform('store.finance.manage');
        $data=$request->validate(['reference'=>'nullable|string|max:120|regex:/^[a-zA-Z0-9_-]+$/','capture_reference'=>'nullable|string|max:120|regex:/^[a-zA-Z0-9_-]+$/']);
        $payment=\Modules\Payment\Models\PaymentTransaction::withoutGlobalScopes()->where('payable_type',StoreOrder::class)->findOrFail($id);
        $result=app(\Modules\Payment\Services\PaymentVerificationService::class)->verify($payment,$data);
        if(!($result['success']??false))throw \Illuminate\Validation\ValidationException::withMessages(['payment'=>$result['error']??'لم يثبت المزود مطابقة التحصيل.']);
        return response()->json(['data'=>$result]);
    }
    public function returns(Request $request) {
        $this->access->platform('store.orders.manage');
        return response()->json(['data'=>\Modules\Store\Models\StoreReturn::with('item:id,store_order_id,store_sub_order_id,company_id,product_name_snapshot')->latest('id')->paginate(25)]);
    }
    public function updateReturn(Request $request,string $id) {
        $this->access->platform('store.orders.manage');
        $data=$request->validate(['action'=>'required|in:approve,reject,receive','confirmed'=>'required|accepted',
            'condition'=>'required_if:action,receive|in:saleable,damaged','reason'=>'required_if:action,reject|nullable|string|max:2000','notes'=>'nullable|string|max:2000']);
        return response()->json(['data'=>app(\Modules\Store\Services\StoreReturnService::class)->act(\Modules\Store\Models\StoreReturn::findOrFail($id),$data)]);
    }
    public function refunds() {
        $this->access->platform('store.finance.manage');
        return response()->json(['data'=>\Modules\Store\Models\StoreRefund::latest('id')->paginate(25)]);
    }
    public function updateRefund(Request $request,string $id) {
        $this->access->platform('store.finance.manage');
        $data=$request->validate(['action'=>'required|in:retry,confirm','confirmed'=>'required|accepted','reference'=>'required_if:action,confirm|nullable|string|max:150',
            'payer'=>'required_if:action,confirm|in:platform,seller,gateway']);
        $refund=\Modules\Store\Models\StoreRefund::findOrFail($id);
        $service=app(\Modules\Store\Services\StoreRefundService::class);
        if($data['action']==='confirm') $service->confirmManual($refund,$data['reference'],$data['payer']);
        else {
            if(!$refund->payment_transaction_id) throw \Illuminate\Validation\ValidationException::withMessages(['refund'=>'هذه عملية استرداد دفع عند الاستلام؛ تحتاج إثبات التحويل للعميل.']);
            app(\Modules\Store\Services\StoreOutboxService::class)->enqueue('refund.process','refund:'.$refund->id.':review:'.\Illuminate\Support\Str::uuid(),['refund_id'=>$refund->id]);
        }
        return response()->json(['data'=>$refund->refresh()]);
    }
    public function payouts() {
        $this->access->platform('store.finance.manage');
        return response()->json(['success'=>true,'data'=>\Modules\Store\Models\StorePayout::join('store_seller_accounts as a','a.id','=','store_payouts.account_id')->select('store_payouts.*','a.company_id')->latest('store_payouts.id')->paginate(30)]);
    }
    public function requestPayout(Request $request) {
        $this->access->platform('store.finance.manage');
        $data=$request->validate(['company_id'=>'required|integer|exists:companies,id','amount'=>'required|decimal:0,2|gt:0|max:9999999999.99','operation_key'=>'required|uuid']);
        return response()->json(['success'=>true,'data'=>app(\Modules\Store\Services\StorePayoutService::class)->request((int)$data['company_id'],Money::minor((string)$data['amount']),$data['operation_key'])]);
    }
    public function payout(Request $request,string $id) {
        $this->access->platform('store.finance.manage');
        $payout=\Modules\Store\Models\StorePayout::findOrFail($id);
        $payout->setAttribute('company_id',StoreSellerAccount::findOrFail($payout->account_id)->company_id);
        return response()->json(['success'=>true,'data'=>['payout'=>$payout,'destination'=>$payout->destination_snapshot]]);
    }
    public function updatePayout(Request $request,string $id) {
        $this->access->platform('store.finance.manage');
        $data=$request->validate(['action'=>'required|in:approve,paid,reject','reference'=>'required_if:action,paid|nullable|string|max:150',
            'confirmed'=>$request->input('action')==='approve'?'nullable|boolean':'required|accepted','reason'=>'required_if:action,reject|nullable|string|max:300']);
        $payout=\Modules\Store\Models\StorePayout::findOrFail($id); $service=app(\Modules\Store\Services\StorePayoutService::class);
        match($data['action']) {
            'approve'=>$service->approve($payout), 'paid'=>$service->paid($payout,$data['reference']), 'reject'=>$service->reject($payout,$data['reason']),
        };
        return $this->payout($request,$id);
    }
    public function __construct(private StoreAccess $access) {}
    public function bootstrap() {
        abort_unless(collect(['store.settings.manage','store.orders.manage','store.finance.manage'])->contains(fn($p)=>$this->access->canPlatform($p)),403);
        return response()->json(['success'=>true,'data'=>[
            'permissions'=>['settings'=>$this->access->canPlatform(),'orders'=>$this->access->canPlatform('store.orders.manage'),'finance'=>$this->access->canPlatform('store.finance.manage')],
            'settings'=>app(StoreSettingsService::class)->get(),'readiness_issues'=>app(StoreReadinessService::class)->issues(),'platform_company_id'=>(int)config('app.master_company_id',1),
            'scheduler'=>\Modules\Store\Models\StoreSetting::where('key','operations_heartbeat')->value('value'),
            'companies'=>Company::withoutGlobalScopes()->whereNull('deleted_at')->orderBy('name')->get(['id','name']),
            'plans'=>Plan::withoutGlobalScopes()->where('is_active',true)->get(['id','name','features']),
            'legal_documents'=>\Modules\Legal\Models\LegalDocument::withoutGlobalScopes()->whereNull('deleted_at')->where('is_active',true)
                ->where(fn($q)=>$q->whereNull('company_id')->orWhere('company_id',config('app.master_company_id',1)))->get(['id','key']),
            'governorates'=>collect(StoreGeography::governorates())->map(fn($name,$id)=>['id'=>$id,'name'=>$name])->values(),
        ]]);
    }
    public function settings(Request $request) {
        $this->access->platform();
        $rules=[
            'settings'=>'required|array','settings.enabled'=>'required|boolean','settings.currency'=>'required|in:EGP',
            'settings.allow_guest_checkout'=>'required|boolean','settings.require_verified_email'=>'required|boolean',
            'settings.max_unconfirmed_orders_per_customer'=>'required|integer|min:1|max:100',
            'settings.cod_enabled'=>'required|boolean','settings.online_enabled'=>'required|boolean',
            'settings.hold_hours'=>'required|integer|min:1|max:168','settings.quote_minutes'=>'required|integer|min:1|max:60',
            'settings.auto_confirm_paid_orders'=>'required|boolean','settings.platform_shipping_account_id'=>'nullable|integer',
            'settings.legal_setup_confirmed'=>'required|boolean','settings.legal_document_ids'=>'required|array|min:1',
            'settings.legal_document_ids.*'=>'integer|exists:legal_documents,id',
            'settings.tax_mode'=>'required|in:unconfigured,none,included,exclusive','settings.default_tax_rate_bps'=>'required|integer|min:0|max:10000',
            'settings.return_policy'=>'required|array','settings.return_policy.window_days'=>'nullable|integer|min:0|max:365',
            'settings.return_policy.allow_change_of_mind'=>'required|boolean','settings.return_policy.allow_defect'=>'required|boolean',
            'settings.seller_policy'=>'required|array',
        ]+StorePolicyRules::rules('settings.seller_policy.');
        $data=$request->validate($rules)['settings'];
        $data=array_intersect_key($data,app(StoreSettingsService::class)->defaults());
        if($data['platform_shipping_account_id']) StoreShippingAccount::where('company_id',config('app.master_company_id',1))->findOrFail($data['platform_shipping_account_id']);
        DB::transaction(function() use($data) {
            app(StoreSettingsService::class)->save($data);
            if($data['enabled']&&($issues=app(StoreReadinessService::class)->issues()))
                throw \Illuminate\Validation\ValidationException::withMessages(['settings.enabled'=>$issues]);
        });
        return $this->bootstrap();
    }
    public function seller(string $id) {
        $this->access->platform();
        $company=Company::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($id);
        $profile=StoreSellerProfile::firstOrNew(['company_id'=>$company->id]);
        return response()->json(['success'=>true,'data'=>[
            'profile'=>$profile,'payout_destination_configured'=>(bool)$profile->payout_destination,
            'effective_policy'=>app(SellerPolicyService::class)->resolve($company->id),
            'warehouses'=>Warehouse::withoutGlobalScopes()->where('company_id',$company->id)->get(['id','name']),
        ]]);
    }
    public function saveSeller(Request $request,string $id) {
        $this->access->platform();
        if($request->filled('payout_destination'))$this->access->platform('store.finance.manage');
        $company=Company::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($id);
        $data=$request->validate([
            'is_enabled'=>'required|boolean','warehouse_id'=>'nullable|integer','shipping_account_id'=>'nullable|integer',
            'pickup_address'=>'required|array','pickup_address.recipient_name'=>'required|string|max:150',
            'pickup_address.phone'=>'required|string|max:30','pickup_address.street'=>'required|string|max:255',
            'pickup_address.city'=>'required|string|max:100','pickup_address.governorate_code'=>['required',Rule::in(array_keys(StoreGeography::governorates()))],
            'pickup_address.district'=>'nullable|string|max:100','pickup_address.building'=>'nullable|string|max:100',
            'policy_overrides'=>'present|array','payout_destination'=>'nullable|array','payout_destination.bank_name'=>'required_with:payout_destination|string|max:150',
            'payout_destination.account_holder'=>'required_with:payout_destination|string|max:150',
            'payout_destination.account_number'=>'nullable|string|max:80','payout_destination.iban'=>'nullable|string|max:80',
        ]+StorePolicyRules::rules('policy_overrides.',true));
        if(!empty($data['warehouse_id'])) Warehouse::withoutGlobalScopes()->where('company_id',$company->id)->findOrFail($data['warehouse_id']);
        if(!empty($data['shipping_account_id'])) StoreShippingAccount::whereIn('company_id',[$company->id,config('app.master_company_id',1)])->findOrFail($data['shipping_account_id']);
        if(!empty($data['payout_destination'])) {
            $this->access->platform('store.finance.manage');
            if(empty($data['payout_destination']['account_number'])&&empty($data['payout_destination']['iban']))
                throw \Illuminate\Validation\ValidationException::withMessages(['payout_destination'=>'رقم الحساب أو IBAN مطلوب.']);
        }
        $profile=StoreSellerProfile::firstOrCreate(['company_id'=>$company->id]);
        $data['policy_version']=$profile->policy_version+1;
        $data['policy_review_required']=false;
        $data['policy_overrides']=array_filter(array_intersect_key($data['policy_overrides'],app(StoreSettingsService::class)->defaults()['seller_policy']),fn($v)=>$v!==null);
        $profile->update($data);
        return $this->seller($id);
    }
    public function shippingAccounts() {
        $this->access->platform();
        return response()->json(['success'=>true,'data'=>StoreShippingAccount::orderBy('company_id')->get()->map(fn($account)=>[
            'id'=>$account->id,'company_id'=>$account->company_id,'name'=>$account->name,'driver'=>$account->driver,
            'cod_beneficiary'=>$account->cod_beneficiary,'is_active'=>$account->is_active,'is_test_mode'=>$account->is_test_mode,
            'credentials_configured'=>(bool)$account->credentials,'configuration'=>$account->configuration,
        ])]);
    }
    public function saveShippingAccount(Request $request,?string $id=null) {
        $this->access->platform();
        $definitions=\Modules\Store\Services\Shipping\ShippingGatewayRegistry::definitions();
        $data=$request->validate(['company_id'=>'required|integer|exists:companies,id','name'=>'required|string|max:150',
            'driver'=>['required',Rule::in(array_keys($definitions))],'cod_beneficiary'=>'required|in:seller,platform',
            'is_active'=>'required|boolean','is_test_mode'=>'required|boolean','credentials'=>'nullable|array','configuration'=>'nullable|array']);
        $account=$id?StoreShippingAccount::findOrFail($id):new StoreShippingAccount();
        if($account->exists&&($account->driver!==$data['driver']||(int)$account->company_id!==(int)$data['company_id']))
            throw \Illuminate\Validation\ValidationException::withMessages(['account'=>'أضف حسابًا جديدًا لتغيير المزود أو مالك العقد.']);
        if(empty($data['credentials'])) unset($data['credentials']);
        $account->fill($data)->save();
        return $this->shippingAccounts();
    }
    public function rates(string $id) {
        $this->access->platform(); StoreShippingAccount::findOrFail($id);
        return response()->json(['success'=>true,'data'=>StoreShippingRate::where('shipping_account_id',$id)->get()]);
    }
    public function saveRate(Request $request,string $id) {
        $this->access->platform(); $account=StoreShippingAccount::findOrFail($id);
        $data=$request->validate(['governorate_code'=>['required',Rule::in(array_keys(StoreGeography::governorates()))],
            'scope_company_id'=>['sometimes','integer','min:0',function($attribute,$value,$fail)use($account){
                if($value>0&&(!Company::withoutGlobalScopes()->whereNull('deleted_at')->whereKey($value)->exists()
                    ||((int)$account->company_id!==(int)config('app.master_company_id',1)&&(int)$value!==(int)$account->company_id)))$fail('استثناء الشحن يجب أن يخص بائعًا موجودًا وحساب شحنه أو حساب الإدارة.');
            }],
            'fee'=>'required|decimal:0,2|min:0|max:9999999999.99','free_above'=>'nullable|decimal:0,2|min:0|max:9999999999.99',
            'carrier_fee'=>'nullable|decimal:0,2|min:0|max:9999999999.99','subsidy_payer'=>'nullable|in:seller,platform',
            'is_active'=>'required|boolean','cod_allowed'=>'required|boolean']);
        \Illuminate\Support\Facades\DB::transaction(function()use($id,$data){
        StoreShippingAccount::lockForUpdate()->findOrFail($id);
        $base=StoreShippingRate::where('shipping_account_id',$id)->where('governorate_code',$data['governorate_code'])->where('scope_company_id',0)->first();
        StoreShippingRate::updateOrCreate(['shipping_account_id'=>$id,'governorate_code'=>$data['governorate_code'],'scope_company_id'=>$data['scope_company_id']??0],[
            'fee_minor'=>Money::minor((string)$data['fee']),'free_above_minor'=>isset($data['free_above'])?Money::minor((string)$data['free_above']):null,
            'carrier_fee_minor'=>isset($data['carrier_fee'])?Money::minor((string)$data['carrier_fee']):null,'subsidy_payer'=>$data['subsidy_payer']??$base?->subsidy_payer??'seller',
            'is_active'=>$data['is_active'],'cod_allowed'=>$data['cod_allowed'],
        ]);
        });
        return $this->rates($id);
    }
    public function orders(Request $request) {
        $this->access->platform('store.orders.manage');
        $query=StoreOrder::with(['subOrders.company','subOrders.items','refunds'])->latest('id');
        if($request->filled('status')) $query->where('status',$request->string('status'));
        if($request->filled('search')) $query->where('order_number','like','%'.substr($request->string('search'),0,60).'%');
        $request->attributes->set('store_staff_view',true);
        $orders=$query->paginate(25);
        return response()->json(['success'=>true,'data'=>StoreOrderResource::collection($orders)->response()->getData(true)]);
    }
    public function order(Request $request,string $id) {
        $this->access->platform('store.orders.manage'); $request->attributes->set('store_staff_view',true);
        $order=StoreOrder::with(['subOrders.company','subOrders.items','refunds'])->findOrFail($id);
        return response()->json(['success'=>true,'data'=>['order'=>new StoreOrderResource($order),
            'shipments'=>StoreShipment::whereIn('store_sub_order_id',$order->subOrders->pluck('id'))->get(),
            'events'=>DB::table('store_order_events')->where('store_order_id',$order->id)->orderBy('id')->get()]]);
    }
    public function confirm(Request $request,string $id) {
        $this->access->platform('store.orders.manage');
        $data=$request->validate(['phone_confirmed'=>'required|boolean']);
        app(StoreOrderService::class)->confirm(StoreOrder::findOrFail($id),$data['phone_confirmed']);
        return $this->order($request,$id);
    }
    public function transition(Request $request,string $id) {
        $this->access->platform('store.orders.manage');
        $data=$request->validate(['status'=>'required|in:confirmed,processing,shipped,delivered,cancelled',
            'phone_confirmed'=>'nullable|boolean','collected'=>'nullable|boolean','tracking_number'=>'nullable|string|max:100',
            'shipping_carrier'=>'nullable|string|max:150','reason'=>'nullable|string|max:300']);
        $sub=StoreSubOrder::findOrFail($id);
        app(StoreOrderService::class)->updateSubOrderStatus($sub,$data['status'],$data);
        return $this->order($request,(string)$sub->store_order_id);
    }
    public function dispatch(Request $request,string $id) {
        $this->access->platform('store.orders.manage'); $data=$request->validate(['tracking_number'=>'required|string|max:100']);
        $shipment=StoreShipment::findOrFail($id); app(StoreShipmentService::class)->dispatchManual($shipment,$data['tracking_number']);
        return $this->order($request,(string)StoreSubOrder::findOrFail($shipment->store_sub_order_id)->store_order_id);
    }
    public function cancelShipment(Request $request,string $id) {
        $this->access->platform('store.orders.manage'); $data=$request->validate(['confirmed'=>'required|accepted']);
        $shipment=StoreShipment::findOrFail($id); app(StoreShipmentService::class)->cancel($shipment,(bool)$data['confirmed']);
        return response()->json(['success'=>true]);
    }
    public function accounts() {
        $this->access->platform('store.finance.manage');
        return response()->json(['success'=>true,'data'=>StoreSellerAccount::orderBy('company_id')->get()->map(function($account) {
            $account->setAttribute('available_for_payout_minor',app(\Modules\Store\Services\StorePayoutService::class)->available($account)); return $account;
        })]);
    }
    public function ledger(string $id) {
        $this->access->platform('store.finance.manage');
        $account=StoreSellerAccount::where('company_id',$id)->first();
        return response()->json(['success'=>true,'data'=>$account?DB::table('store_seller_ledger')->where('account_id',$account->id)->latest('id')->paginate(30):['data'=>[]]]);
    }
    public function topup(Request $request,string $id) {
        $this->access->platform('store.finance.manage'); Company::withoutGlobalScopes()->findOrFail($id);
        $data=$request->validate(['amount'=>'required|decimal:0,2|gt:0|max:9999999999.99','operation_key'=>'required|uuid','reference'=>'required|string|max:150','purpose'=>'nullable|in:topup,debt']);
        $account=app(StoreLedgerService::class)->topUp((int)$id,Money::minor((string)$data['amount']),$data['operation_key'],$data['reference'],app(StoreSettingsService::class)->get()['currency'],($data['purpose']??'topup')==='debt');
        return response()->json(['success'=>true,'data'=>$account]);
    }
    public function collection(Request $request,string $id) {
        $this->access->platform('store.finance.manage'); $request->validate(['confirmed'=>'required|accepted']);
        $sub=StoreSubOrder::findOrFail($id);
        DB::transaction(function() use($sub) { StoreOrder::lockForUpdate()->findOrFail($sub->store_order_id); app(StorePaymentService::class)->confirmCodCollection($sub); },3);
        return $this->order($request,(string)$sub->store_order_id);
    }
    public function remittance(Request $request,string $id) {
        $this->access->platform('store.finance.manage'); $data=$request->validate(['reference'=>'required|string|max:150',
            'net_received'=>'required|decimal:0,2|min:0|max:9999999999.99','fee_withheld'=>'required|decimal:0,2|min:0|max:9999999999.99','fee_variance_reason'=>'nullable|string|max:500']);
        $sub=StoreSubOrder::findOrFail($id); app(StorePaymentService::class)->confirmCourierRemittance($sub,$data['reference'],Money::minor((string)$data['net_received']),Money::minor((string)$data['fee_withheld']),$data['fee_variance_reason']??'');
        return $this->order($request,(string)$sub->store_order_id);
    }
    public function outbox() {
        $this->access->platform('store.orders.manage');
        return response()->json(['success'=>true,'data'=>StoreOutbox::select(['id','kind','status','attempts','available_at','last_error','updated_at'])
            ->where('status','!=','completed')->latest('id')->paginate(30)]);
    }
    public function retry(string $id) {
        $this->access->platform('store.orders.manage');
        $entry=StoreOutbox::findOrFail($id);
        if(in_array($entry->kind,['refund.process'],true)) $this->access->platform('store.finance.manage');
        if($entry->status==='processing'&&$entry->locked_until?->isFuture()) abort(409);
        $entry->update(['status'=>'pending','attempts'=>0,'available_at'=>now('UTC'),'locked_until'=>null,'lock_token'=>null,'last_error'=>null]);
        return response()->json(['success'=>true]);
    }
}
