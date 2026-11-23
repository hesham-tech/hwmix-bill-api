<?php
namespace Modules\Guidance\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Guidance\Services\CompanySetupService;
class CompanySetupController extends Controller {
    public function compact(Request $request,\Modules\Guidance\Services\CompactCompanySetupService $service){return response()->json(['data'=>$service->compact($request->user())]);}
    public function saveCompact(Request $request,\Modules\Guidance\Services\CompactCompanySetupService $service,string $step){
        $v=$request->validate(['revision'=>'required|string','data'=>'present|array','defer'=>'sometimes|boolean']);
        return response()->json(['data'=>$service->saveCompact($request->user(),$step,$v['data'],$v['revision'],(bool)($v['defer']??false))]);
    }
    public function option(Request $request,\Modules\Guidance\Services\CompactCompanySetupService $service,string $section){
        $v=$request->validate(['revision'=>'required|string','data'=>'present|array']);
        return response()->json(['data'=>$service->option($request->user(),$section,$v['data'],$v['revision'])]);
    }
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
