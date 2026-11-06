<?php
namespace Modules\Store\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Store\Services\StoreAccountService;
class StoreAccountController extends Controller {
    public function status(Request $request) {
        return response()->json(['success'=>true,'data'=>['email'=>$request->user()->email,
            'email_verified'=>(bool)$request->user()->email_verified_at]]);
    }
    public function send(Request $request,StoreAccountService $accounts) {
        $data=$request->validate(['email'=>'nullable|email|max:255','password'=>'nullable|string|max:255']);
        return response()->json(['success'=>true,'data'=>$accounts->request($request->user(),$data['email']??null,$data['password']??null)]);
    }
    public function verify(Request $request,StoreAccountService $accounts) {
        $data=$request->validate(['code'=>'required|digits:8']);
        $user=$accounts->verify($request->user(),$data['code']);
        return response()->json(['success'=>true,'data'=>['email'=>$user->email,'email_verified'=>true,'email_verified_at'=>$user->email_verified_at]]);
    }
}
