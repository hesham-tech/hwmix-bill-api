<?php
namespace Modules\Store\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Store\Models\StoreSubOrder;
use Modules\Store\Services\{StoreAccess,StoreOrderService,StoreLedgerService,SellerPolicyService};
use Modules\Store\Transformers\StoreSubOrderResource;

class VendorOrderController extends Controller {
    public function __construct(private StoreAccess $access) {}
    public function index(Request $request) {
        $companyId=$this->access->seller();
        $request->attributes->set('store_staff_view',true);
        $orders=StoreSubOrder::where('company_id',$companyId)->with(['items','order','company'])->latest('id')
            ->paginate(max(1,min(50,(int)$request->input('per_page',15))));
        return response()->json(['success'=>true,'data'=>StoreSubOrderResource::collection($orders)->response()->getData(true)]);
    }
    public function show(Request $request,string $id) {
        $companyId=$this->access->seller();
        $request->attributes->set('store_staff_view',true);
        return response()->json(['success'=>true,'data'=>new StoreSubOrderResource(StoreSubOrder::where('company_id',$companyId)->with(['items','order','company'])->findOrFail($id))]);
    }
    public function updateStatus(Request $request,string $id,StoreOrderService $orders) {
        $companyId=$this->access->seller('store.vendor.orders.manage');
        $data=$request->validate(['status'=>'required|in:processing,cancelled','reason'=>'required_if:status,cancelled|nullable|string|max:300']);
        $sub=StoreSubOrder::where('company_id',$companyId)->findOrFail($id);
        if($data['status']==='processing') $orders->sellerReady($sub);
        else \Illuminate\Support\Facades\DB::transaction(function()use($orders,$sub,$data){$orders->cancelSubOrder($sub,'seller_unavailable');$orders->event($sub->order,'seller.cancellation_reason',['sub_order_id'=>$sub->id,'reason'=>$data['reason']],$sub->company_id);});
        $request->attributes->set('store_staff_view',true);
        return response()->json(['success'=>true,'data'=>new StoreSubOrderResource($sub->refresh()->load(['items','order','company']))]);
    }
    public function account(StoreLedgerService $ledger,SellerPolicyService $policies,Request $request) {
        $companyId=$this->access->seller('store.vendor.finance.view');
        $account=$ledger->account($companyId,app(\Modules\Store\Services\StoreSettingsService::class)->get()['currency']);
        $entries=\Illuminate\Support\Facades\DB::table('store_seller_ledger')->where('account_id',$account->id)->latest('id')->paginate(30);
        $profile=\Modules\Store\Models\StoreSellerProfile::where('company_id',$companyId)->first();
        return response()->json(['success'=>true,'data'=>['account'=>$account,'policy'=>$policies->resolve($companyId),'ledger'=>$entries,
            'available_for_payout_minor'=>app(\Modules\Store\Services\StorePayoutService::class)->available($account),
            'destination_configured'=>!empty($profile?->payout_destination),'policy_review_required'=>(bool)$profile?->policy_review_required,
            'payouts'=>\Modules\Store\Models\StorePayout::where('account_id',$account->id)->latest('id')->limit(30)->get()]]);
    }
    public function requestPayout(Request $request,\Modules\Store\Services\StorePayoutService $payouts) {
        $companyId=$this->access->seller('store.vendor.finance.manage');
        $data=$request->validate(['amount'=>'required|decimal:0,2|min:0.01|max:9999999999.99','operation_key'=>'required|uuid']);
        return response()->json(['data'=>$payouts->request($companyId,\App\Support\Money::minor((string)$data['amount']),'vendor:'.$companyId.':'.$data['operation_key'])],201);
    }
}
