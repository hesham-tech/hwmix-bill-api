<?php
namespace Modules\Store\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Store\Http\Requests\{PlaceOrderRequest,QuoteOrderRequest};
use Modules\Store\Models\StoreOrder;
use Modules\Store\Services\{StoreOrderService,StoreQuoteService,StorePaymentService,StoreSettingsService,StoreGeography};
use Modules\Store\Transformers\StoreOrderResource;

class StoreOrderController extends Controller
{
    public function __construct(private StoreOrderService $orders) {}
    private function owned(Request $request,string $id): StoreOrder {
        $order=StoreOrder::with(['subOrders.items','subOrders.company','shippingAddress','refunds'])
            ->where(function($q) use($id) { $q->where('order_number',$id); if(ctype_digit($id)) $q->orWhere('id',(int)$id); })->firstOrFail();
        $user=Auth::guard('sanctum')->user();
        if($order->customer_user_id) {
            abort_unless($user&&(int)$order->customer_user_id===(int)$user->id,404);
            return $order;
        }
        $token=(string)$request->header('X-Order-Token','');
        abort_unless(preg_match('/^[a-f0-9]{64}$/D',$token)&&$order->tracking_token_hash
            &&hash_equals($order->tracking_token_hash,hash('sha256',$token)),404);
        return $order;
    }
    public function index(Request $request) {
        $user=Auth::guard('sanctum')->user();
        if(!$user) return response()->json(['success'=>true,'data'=>['data'=>[],'meta'=>['total'=>0]]]);
        $orders=StoreOrder::where('customer_user_id',$user->id)->with(['subOrders.items','subOrders.company'])
            ->latest('id')->paginate(max(1,min(50,(int)$request->input('per_page',15))));
        return response()->json(['success'=>true,'data'=>StoreOrderResource::collection($orders)->response()->getData(true)]);
    }
    public function configuration(StoreSettingsService $settings,StoreQuoteService $quotes,StorePaymentService $payments) {
        $config=$settings->get(); $methods=$payments->methods($config);$gateways=$methods['gateways'];
        return response()->json(['success'=>true,'data'=>[
            'enabled'=>$config['enabled']&&$config['legal_setup_confirmed']&&$config['tax_mode']!=='unconfigured'&&(bool)$quotes->documents()
                &&(!app()->environment('production')||!app(\Modules\Store\Services\StoreOperationalHealth::class)->issues()),
            'currency'=>$config['currency'],'cod_enabled'=>$methods['cod_enabled'],'online_enabled'=>(bool)$gateways,
            'requires_account'=>!$config['allow_guest_checkout'],'requires_verified_email'=>$config['require_verified_email'],
            'gateways'=>$gateways,'governorates'=>collect(StoreGeography::governorates())->map(fn($name,$id)=>['id'=>$id,'name'=>$name])->values(),
            'legal_documents'=>$quotes->documents(),
        ]]);
    }
    public function quote(QuoteOrderRequest $request,StoreQuoteService $quotes) {
        $quote=$quotes->create($request->validated(),Auth::guard('sanctum')->user());
        $snapshot=$quote->snapshot;
        return response()->json(['success'=>true,'data'=>[
            'id'=>$quote->id,'expires_at'=>$quote->expires_at,'currency'=>$snapshot['currency'],
            'subtotal_minor'=>$snapshot['subtotal_minor'],'tax_minor'=>$snapshot['tax_minor'],
            'shipping_minor'=>$snapshot['shipping_minor'],'total_minor'=>$snapshot['total_minor'],
            'legal_documents'=>$snapshot['legal'],'groups'=>array_map(fn($g)=>[
                'company_id'=>$g['company_id'],'subtotal_minor'=>$g['subtotal_minor'],'tax_minor'=>$g['tax_minor'],
                'delivery_method'=>$g['shipping']['delivery_method']??'carrier','shipping_minor'=>$g['shipping']['fee_minor'],'carrier_name'=>$g['shipping']['carrier_name'],
                'items'=>array_map(fn($i)=>array_intersect_key($i,array_flip(['product_id','variant_id','product_name','quantity','unit_price_minor','total_minor','unit_name'])),$g['items']),
            ],$snapshot['groups']),
        ]]);
    }
    public function store(PlaceOrderRequest $request) {
        $order=$this->orders->placeOrder($request->validated(),Auth::guard('sanctum')->user());
        return response()->json(['success'=>true,'message'=>'تم إنشاء الطلب وحجز المنتجات.',
            'data'=>new StoreOrderResource($order),'access_token'=>$order->customer_user_id?null:$this->orders->trackingToken($order)],201);
    }
    public function show(Request $request,string $id) {
        return response()->json(['success'=>true,'data'=>new StoreOrderResource($this->owned($request,$id))]);
    }
    public function cancel(Request $request,string $id) {
        $order=$this->owned($request,$id);
        $this->orders->cancelOrder($order);
        return response()->json(['success'=>true,'data'=>new StoreOrderResource($order->refresh()->load(['subOrders.items','subOrders.company']))]);
    }
    public function pay(Request $request,string $id,StorePaymentService $payments) {
        $data=$request->validate(['payment_gateway_id'=>'nullable|integer']);
        return response()->json(['success'=>true,'data'=>$payments->start($this->owned($request,$id),(int)($data['payment_gateway_id']??0))]);
    }
    public function returns(Request $request,string $id) {
        $order=$this->owned($request,$id);
        return response()->json(['data'=>\Modules\Store\Models\StoreReturn::whereHas('item',fn($q)=>$q->where('store_order_id',$order->id))->orderByDesc('id')->get()->map(fn($return)=>$this->customerReturn($return))]);
    }
    private function customerReturn(\Modules\Store\Models\StoreReturn $return): array {
        return $return->only(['id','store_order_item_id','quantity','reason','status','shipping_payer','refund_minor','created_at','received_at','completed_at']);
    }
    public function requestReturn(Request $request,string $id,\Modules\Store\Services\StoreReturnService $service) {
        $data=$request->validate(['item_id'=>'required|integer','operation_key'=>'required|uuid','quantity'=>'required|integer|min:1|max:9999',
            'reason'=>'required|in:defect,wrong_item,change_of_mind','notes'=>'nullable|string|max:2000']);
        return response()->json(['data'=>$this->customerReturn($service->request($this->owned($request,$id),$data))],201);
    }
    public function legal(string $id,StoreQuoteService $quotes) {
        abort_unless(in_array((int)$id,array_column($quotes->documents(),'version_id'),true),404);
        $version=\Modules\Legal\Models\LegalDocumentVersion::withoutGlobalScopes()->findOrFail($id);
        return response()->json(['success'=>true,'data'=>['id'=>$version->id,'title'=>$version->title,'content'=>$version->content]]);
    }
}
