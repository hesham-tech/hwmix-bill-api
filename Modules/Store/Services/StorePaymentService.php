<?php
namespace Modules\Store\Services;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payment\Models\{PaymentGateway,PaymentTransaction};
use Modules\Payment\Services\{GatewayRegistry,PaymentGatewayFactory,PaymentSessionService};
use Modules\Store\Models\{StoreOrder,StoreSubOrder};

class StorePaymentService
{
    public function __construct(private StoreLedgerService $ledger,private SellerFinanceService $finance,
        private StoreSettingsService $settings,private StoreOutboxService $outbox) {}
    public function gateways(): array {
        return PaymentGateway::withoutGlobalScopes()->whereNull('deleted_at')->where('is_active',true)
            ->where('company_id',config('app.master_company_id',1))->orderByDesc('is_default')->get()
            ->filter(fn($gateway)=>!app()->environment('production') || !$gateway->is_test_mode)
            ->filter(fn($gateway)=>GatewayRegistry::ready($gateway->driver,PaymentGatewayFactory::configuration($gateway)))
            ->map(fn($gateway)=>['id'=>$gateway->id,'name'=>$gateway->name,'driver'=>$gateway->driver,
                'is_default'=>$gateway->is_default,'test_mode'=>$gateway->is_test_mode])->values()->all();
    }
    public function start(StoreOrder $order,int $gatewayId=0): array {
        if($order->payment_method!=='online'||$order->payment_status!=='unpaid'||$order->status==='cancelled'||$order->hold_expires_at?->isPast())
            throw ValidationException::withMessages(['payment'=>'الطلب غير قابل لبدء الدفع.']);
        if(!$order->checkout_key) throw ValidationException::withMessages(['payment'=>'هذا طلب قديم يحتاج مراجعة الإدارة قبل الدفع.']);
        $address=$order->shipping_address_snapshot;
        $parts=preg_split('/\\s+/u',trim($address['recipient_name']),2);
        $options=['success_url'=>rtrim(config('store.frontend_url'),'/').'/store/payment-return',
                'cancel_url'=>rtrim(config('store.frontend_url'),'/').'/store/payment-return','billing_data'=>[
                'first_name'=>$parts[0],'last_name'=>$parts[1]??'NA','phone_number'=>$address['phone'],
                'email'=>$order->customer?->email?:'NA','city'=>$address['city'],'street'=>$address['street'],
                'building'=>$address['building']??'NA','floor'=>$address['floor']??'NA','apartment'=>$address['apartment']??'NA',
                'state'=>StoreGeography::governorates()[$address['governorate_code']]??$address['city'],'country'=>'EG',
            ]];
        $existing=PaymentTransaction::withoutGlobalScopes()->where('payable_type',$order->getMorphClass())->where('payable_id',$order->id)
            ->whereNotIn('status',['failed','expired','cancelled'])->latest('id')->first();
        if($existing) return app(PaymentSessionService::class)->resume($existing,$options);
        if(!$this->settings->get()['online_enabled']) throw ValidationException::withMessages(['payment'=>'الدفع الإلكتروني متوقف للطلبات الجديدة حاليًا.']);
        $gateway=PaymentGateway::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id',config('app.master_company_id',1))->where('is_active',true)->findOrFail($gatewayId);
        $last=PaymentTransaction::withoutGlobalScopes()->where('payable_type',$order->getMorphClass())->where('payable_id',$order->id)->latest('id')->first();
        $key=$last?hash('sha256',$order->checkout_key.':retry:'.$last->id):$order->checkout_key;
        return app(PaymentSessionService::class)->start($order,$gateway,(string)$order->total_amount,$order->currency,$key,$options);
    }
    public function confirmOnlinePayment(StoreOrder $order,PaymentTransaction $transaction): void {
        $order=StoreOrder::withTrashed()->lockForUpdate()->findOrFail($order->id);
        if($order->payment_status==='paid') return;
        if($transaction->status!=='completed'||$transaction->payable_type!==$order->getMorphClass()
            ||(int)$transaction->payable_id!==$order->id||Money::minor($transaction->amount)!==Money::minor($order->total_amount)
            ||$transaction->currency!==$order->currency)
            throw ValidationException::withMessages(['payment'=>'الدفع لا يطابق قيمة الطلب وعملته.']);
        $amount=Money::minor($transaction->amount);
        $this->ledger->journal('payment:'.$transaction->id.':receipt','customer_payment',$order->currency,[
            ['account_code'=>'asset:gateway_receivable','amount_minor'=>$amount],
            ['account_code'=>'clearing:customer_receipts','amount_minor'=>-$amount],
        ],['order_id'=>$order->id,'transaction_id'=>$transaction->id]);
        $order->update(['payment_status'=>'paid']);
        if($order->deleted_at||$order->status==='cancelled'||$order->hold_expires_at?->isPast()) {
            app(StoreRefundService::class)->latePayment($order,$transaction);
            return;
        }
        foreach($order->subOrders()->where('status','!=','cancelled')->orderBy('company_id')->get() as $sub) {
            $this->finance->allocatePaid($sub);
            $sub->update(['collected_at'=>now('UTC')]);
        }
        foreach ($order->subOrders()->where('status','cancelled')->get() as $sub) app(StoreRefundService::class)->unallocatedSubOrder($sub,$transaction);
        app(StoreOrderService::class)->event($order,'payment.verified',['transaction_id'=>$transaction->id]);
        if($this->settings->get()['auto_confirm_paid_orders']) app(StoreOrderService::class)->confirm($order);
        $this->outbox->enqueue('order.customer_notification','paid:'.$order->id,['order_id'=>$order->id,'event'=>'payment_confirmed']);
    }
    public function confirmCodCollection(StoreSubOrder $sub): void {
        $sub=StoreSubOrder::lockForUpdate()->findOrFail($sub->id);
        $order=$sub->order;
        if($order->payment_method!=='cod'||$sub->status!=='delivered')
            throw ValidationException::withMessages(['collection'=>'لا يمكن إثبات تحصيل هذه الشحنة الآن.']);
        if($sub->collected_at) return;
        $sub->update(['collected_at'=>now('UTC')]);
        $policy=$sub->financial_policy_snapshot;
        if($policy['cod_beneficiary']==='seller') {
            if($policy['commission_timing']==='delivered_collected'||($policy['commission_timing']==='remitted'&&$sub->shipment?->delivery_method==='seller_local')) $this->finance->recognizeCod($sub);
        } else {
            $gross=Money::minor($sub->subtotal)+Money::minor($sub->tax_total)+Money::minor($sub->shipping_cost)-Money::minor($sub->discount_total);
            $this->ledger->journal('cod:'.$sub->id.':receipt','cod_customer_collection',$order->currency,[
                ['account_code'=>'asset:carrier_receivable','amount_minor'=>$gross],
                ['account_code'=>'clearing:customer_receipts','amount_minor'=>-$gross],
            ],['sub_order_id'=>$sub->id]);
            $this->finance->allocatePaid($sub);
            $this->finance->recognizePrepaidCommission($sub);
        }
        $active=$order->subOrders()->where('status','!=','cancelled')->get();
        $order->update(['payment_status'=>$active->every(fn($s)=>$s->collected_at!==null)?'paid':'partial']);
        app(StoreOrderService::class)->event($order,'cod.customer_collected',['sub_order_id'=>$sub->id],$sub->company_id);
        $this->outbox->enqueue('invoice.create','invoice:'.$sub->id,['sub_order_id'=>$sub->id]);
    }
    public function confirmCourierRemittance(StoreSubOrder $sub,string $reference,?int $netReceived=null,int $feeWithheld=0,string $varianceReason=''): void {
        DB::transaction(function() use($sub,$reference,$netReceived,$feeWithheld,$varianceReason) {
            StoreOrder::lockForUpdate()->findOrFail($sub->store_order_id);
            $sub=StoreSubOrder::lockForUpdate()->findOrFail($sub->id);
            $shipment=\Modules\Store\Models\StoreShipment::where('store_sub_order_id',$sub->id)->lockForUpdate()->firstOrFail();
            if($shipment->delivery_method==='seller_local')throw ValidationException::withMessages(['remittance'=>'تحصيل التوصيل الذاتي لا يحتاج توريد شركة شحن.']);
            if(!$sub->collected_at||$sub->order->payment_method!=='cod')
                throw ValidationException::withMessages(['remittance'=>'يجب إثبات تحصيل العميل أولًا.']);
            $gross=Money::minor($sub->subtotal)+Money::minor($sub->tax_total)+Money::minor($sub->shipping_cost)-Money::minor($sub->discount_total);
            $netReceived??=$gross-$feeWithheld;
            if($netReceived<0||$feeWithheld<0||$netReceived+$feeWithheld!==$gross)
                throw ValidationException::withMessages(['remittance'=>'صافي التوريد ورسوم الناقل المحتجزة يجب أن يساويا كامل المبلغ المحصل من العميل. التوريد الجزئي يحتاج مطابقة منفصلة.']);
            if($sub->remitted_at) {
                $saved=$shipment->remittance_snapshot;
                if($saved&&($saved['reference']!==$reference||(int)$saved['net_minor']!==$netReceived||(int)$saved['withheld_minor']!==$feeWithheld))
                    throw ValidationException::withMessages(['remittance'=>'سبق توثيق هذا التوريد بقيم أو مرجع مختلف.']);
                return;
            }
            $policy=$sub->financial_policy_snapshot;
            $platformCarrier=($shipment->quote_snapshot['shipping_fee_beneficiary']??'')==='platform';
            if($feeWithheld>0&&(($policy['cod_beneficiary']==='platform')!==$platformCarrier))
                throw ValidationException::withMessages(['remittance'=>'خصم رسوم في عقد يحوّل التحصيل لجهة مختلفة عن صاحب حساب الناقل يحتاج مطابقة مالية منفصلة. لا تثبت توريدًا إجماليًا لم تستلمه.']);
            $expected=(int)($policy['carrier_fee_minor']??$shipment->quote_snapshot['carrier_fee_minor']??Money::minor($sub->shipping_cost));
            $extra=max(0,$feeWithheld-$expected);
            if($extra&&!trim($varianceReason))throw ValidationException::withMessages(['fee_variance_reason'=>'وضح سبب اعتماد رسوم تتجاوز التكلفة المثبتة. يتحمل الفرق حساب الإدارة مع ناقل الإدارة.']);
            $sub->update(['remitted_at'=>now('UTC')]);
            if($policy['cod_beneficiary']==='seller'&&$policy['commission_timing']==='remitted')$this->finance->recognizeCod($sub);
            $metadata=['reference'=>$reference,'net_minor'=>$netReceived,'withheld_minor'=>$feeWithheld,'extra_fee_minor'=>$extra,'variance_reason'=>$varianceReason,'actor_id'=>auth()->id()];
            if($policy['cod_beneficiary']==='platform') {
                $this->finance->recognizePrepaidCommission($sub);
                $this->ledger->journal('cod:'.$sub->id.':remittance','courier_remittance',$sub->order->currency,[
                    ['account_code'=>'asset:bank_clearing','amount_minor'=>$netReceived],
                    ['account_code'=>'liability:carrier','amount_minor'=>min($feeWithheld,$expected)],
                    ['account_code'=>'expense:carrier_variance','amount_minor'=>$extra],
                    ['account_code'=>'asset:carrier_receivable','amount_minor'=>-$gross],
                ],$metadata);
            } else {
                $operation=app(\App\Contracts\FinancialEngineInterface::class)->postMarketplaceJournal($shipment,$sub->company_id,'marketplace_courier_remittance',[
                    ['type'=>'debit','account_type'=>'asset','sub_account_type'=>'bank_reconciliation_clearing','amount_minor'=>$netReceived],
                    ['type'=>'debit','account_type'=>'liability','sub_account_type'=>'courier_payable','amount_minor'=>min($feeWithheld,$expected)],
                    ['type'=>'debit','account_type'=>'expense','sub_account_type'=>'shipping','amount_minor'=>$extra],
                    ['type'=>'credit','account_type'=>'asset','sub_account_type'=>'courier_receivable','amount_minor'=>$gross],
                ],['amount'=>Money::decimal($gross),'currency'=>$sub->order->currency,'reference'=>$reference,'entry_date'=>$sub->remitted_at]);
                $metadata['erp_operation_id']=$operation;
            }
            $shipment->update(['remittance_snapshot'=>$metadata]);
            app(StoreOrderService::class)->event($sub->order,'cod.courier_remitted',['sub_order_id'=>$sub->id]+$metadata,$sub->company_id);
        },3);
    }
}
