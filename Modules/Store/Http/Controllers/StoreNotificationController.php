<?php
namespace Modules\Store\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Store\Services\StoreAccess;
class StoreNotificationController extends Controller {
    private function query(Request $request,string $scope) {
        $q=DB::table('store_notifications');
        if($scope==='admin') {app(StoreAccess::class)->platform('store.orders.manage');return $q->whereNull('user_id')->where('company_id',config('app.master_company_id',1));}
        if($scope==='vendor')return $q->whereNull('user_id')->where('company_id',app(StoreAccess::class)->seller());
        return $q->where('user_id',$request->user()->id);
    }
    public function index(Request $request,string $scope='customer') {
        $query=$this->query($request,$scope);$unread=(clone $query)->whereNull('read_at')->count();
        return response()->json(['data'=>['unread_count'=>$unread,'notifications'=>$query->select(['id','type','title','message','store_order_id','read_at','created_at'])->latest('id')->paginate(20)]]);
    }
    public function read(Request $request,string $id,string $scope='customer') {
        $row=$this->query($request,$scope)->where('id',$id)->first();abort_unless($row,404);
        $this->query($request,$scope)->where('id',$id)->whereNull('read_at')->update(['read_at'=>now('UTC'),'updated_at'=>now('UTC')]);
        return response()->json(['data'=>['id'=>(int)$id,'read'=>true]]);
    }
}
