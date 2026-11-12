<?php
namespace Modules\Guidance\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Guidance\Services\CompanySetupService;
class CompanySetupController extends Controller {
    public function show(Request $request,CompanySetupService $service){return response()->json(['data'=>$service->snapshot($request->user())]);}
    public function save(Request $request,CompanySetupService $service,string $step){
        $request->validate(['revision'=>'required|string','data'=>'present|array','defer'=>'sometimes|boolean']);
        return response()->json(['data'=>$service->save($request->user(),$step,$request->input('data'),$request->string('revision')->toString(),$request->boolean('defer'))]);
    }
    public function preferences(Request $request,CompanySetupService $service){
        $data=$request->validate(['hidden'=>'required|boolean']);
        return response()->json(['data'=>$service->preferences($request->user(),$data['hidden'])]);
    }
}
