<?php
namespace Modules\Store\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Store\Services\{StoreAccess,LocalDeliveryService,StoreLocalDeliveryOperations};
use Modules\Store\Http\Requests\LocalDeliverySettingsRequest;
use Modules\Store\Models\StoreSubOrder;
use Modules\Store\Transformers\StoreSubOrderResource;
class LocalDeliveryController extends Controller {
    private function company(?string $id): int {
        $access=app(StoreAccess::class);
        if($id!==null){$access->platform();\App\Models\Company::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($id);return (int)$id;}
        return $access->seller('store.vendor.delivery.settings');
    }
    public function show(?string $id=null){return response()->json(['data'=>app(LocalDeliveryService::class)->settings($this->company($id))]);}
    public function save(LocalDeliverySettingsRequest $request,?string $id=null){return response()->json(['data'=>app(LocalDeliveryService::class)->save($this->company($id),$request->validated())]);}
    public function pickup(Request $request,string $id){
        $access=app(StoreAccess::class);
        if(str_starts_with($request->path(),'api/v1/admin/')){$access->platform('store.orders.manage');$company=null;}
        else $company=$access->seller('store.vendor.delivery.pickup');
        $sub=StoreSubOrder::when($company,fn($q)=>$q->where('company_id',$company))->findOrFail($id);
        $data=$request->validate(['confirmed'=>'required|accepted','reason'=>'required|string|max:500','address'=>'required|array:recipient_name,phone,street,city,governorate_code,latitude,longitude,location_confirmed,country,district,building,floor,apartment,landmark',
            'address.recipient_name'=>'required|string|max:150','address.phone'=>'required|string|max:30','address.street'=>'required|string|max:255',
            'address.city'=>'required|string|max:100','address.governorate_code'=>['required',\Illuminate\Validation\Rule::in(array_keys(\Modules\Store\Services\StoreGeography::governorates()))],
            'address.latitude'=>'required|numeric|between:-90,90','address.longitude'=>'required|numeric|between:-180,180','address.location_confirmed'=>'required|accepted',
            'address.country'=>'nullable|in:EG','address.district'=>'nullable|string|max:100','address.building'=>'nullable|string|max:100',
            'address.floor'=>'nullable|string|max:100','address.apartment'=>'nullable|string|max:100','address.landmark'=>'nullable|string|max:500']);
        app(\Modules\Store\Services\StorePickupOverrideService::class)->change($sub,$data['address'],$data['reason']);
        $request->attributes->set('store_staff_view',true);
        return response()->json(['data'=>new StoreSubOrderResource($sub->refresh()->load(['items','order','company','shipment']))]);
    }
    public function action(Request $request,string $id,string $action){
        abort_unless(in_array($action,['dispatch','delivered','collect','return_to_origin'],true),404);
        $access=app(StoreAccess::class);
        if(str_starts_with($request->path(),'api/v1/admin/')){$access->platform('store.orders.manage');if($action==='collect')$access->platform('store.finance.manage');$company=null;}
        else {$company=$access->seller($action==='collect'?'store.vendor.delivery.collect':'store.vendor.delivery.manage');}
        $sub=StoreSubOrder::when($company,fn($q)=>$q->where('company_id',$company))->findOrFail($id);
        $data=$request->validate(['confirmed'=>'required|accepted','reference'=>in_array($action,['dispatch','collect','return_to_origin'],true)?'required|string|max:150':'nullable|string|max:150',
            'condition'=>$action==='return_to_origin'?'required|in:saleable,damaged':'nullable',
            'reason'=>$action==='return_to_origin'?'required|in:customer_refused,unreachable,courier_failed,seller_error,other':'nullable']);
        app(StoreLocalDeliveryOperations::class)->act($sub,$action,$data);
        $request->attributes->set('store_staff_view',true);
        return response()->json(['data'=>new StoreSubOrderResource($sub->refresh()->load(['items','order','company','shipment']))]);
    }
}
