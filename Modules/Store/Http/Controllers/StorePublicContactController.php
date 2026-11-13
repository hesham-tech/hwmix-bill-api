<?php
namespace Modules\Store\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Modules\Store\Models\StoreSetting;
use Modules\Store\Services\StoreAccess;
class StorePublicContactController extends Controller {
    private function contact(): array {
        return Arr::only(StoreSetting::where('key','store_public_contact')->firstOrFail()->value,['email','phone','address']);
    }
    private function authorizeEditor(Request $request): void {
        app(StoreAccess::class)->platform('store.contact.manage');
        $user=$request->user();
        if(!$user->checkPermissionTo('admin.super'))abort_unless($user->companies()->where('companies.id',$user->active_company_id)->wherePivot('status','active')->exists(),403);
    }
    public function show(){return response()->json(['success'=>true,'data'=>$this->contact()]);}
    public function adminShow(Request $request){$this->authorizeEditor($request);return $this->show();}
    public function update(Request $request){
        $this->authorizeEditor($request);
        $data=$request->validate([
            'email'=>'required|email:rfc|max:254',
            'phone'=>['required','string','max:30','regex:/^(?:\+|00)?[0-9][0-9\s()\-]{6,25}$/'],
            'address'=>'required|string|max:500',
        ]);
        StoreSetting::updateOrCreate(['key'=>'store_public_contact'],['value'=>Arr::only($data,['email','phone','address'])]);
        return response()->json(['success'=>true,'message'=>'حُفظت بيانات تواصل المتجر.','data'=>$this->contact()]);
    }
}
