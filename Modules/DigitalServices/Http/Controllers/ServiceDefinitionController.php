<?php

namespace Modules\DigitalServices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceDefinitionController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        
        $query = DB::table('service_definitions')
            ->join('service_providers', 'service_providers.id', '=', 'service_definitions.service_provider_id')
            ->where('service_definitions.company_id', $companyId)
            ->whereNull('service_definitions.deleted_at')
            ->whereNull('service_providers.deleted_at')
            ->select(
                'service_definitions.*',
                'service_providers.name as provider_name'
            )
            ->orderBy('service_definitions.name');

        if ($request->has('service_provider_id')) {
            $query->where('service_definitions.service_provider_id', $request->service_provider_id);
        }
            
        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_provider_id' => 'required|integer|exists:service_providers,id',
            'name' => 'required|string|max:255',
            'operation_type' => 'required|string|in:cash_in,cash_out,bill_payment,transfer',
            'is_active' => 'boolean',
        ]);

        $companyId = $request->user()->company_id;

        $id = DB::table('service_definitions')->insertGetId([
            'company_id' => $companyId,
            'service_provider_id' => $validated['service_provider_id'],
            'name' => $validated['name'],
            'operation_type' => $validated['operation_type'],
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم إضافة الخدمة بنجاح',
            'data' => DB::table('service_definitions')->find($id)
        ]);
    }

    public function update(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        
        $def = DB::table('service_definitions')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$def) abort(404, 'الخدمة غير موجودة');

        $validated = $request->validate([
            'service_provider_id' => 'required|integer|exists:service_providers,id',
            'name' => 'required|string|max:255',
            'operation_type' => 'required|string|in:cash_in,cash_out,bill_payment,transfer',
            'is_active' => 'boolean',
        ]);

        DB::table('service_definitions')->where('id', $id)->update([
            'service_provider_id' => $validated['service_provider_id'],
            'name' => $validated['name'],
            'operation_type' => $validated['operation_type'],
            'is_active' => $validated['is_active'] ?? true,
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم التحديث بنجاح',
            'data' => DB::table('service_definitions')->find($id)
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        
        DB::table('service_definitions')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->update([
                'deleted_at' => now()
            ]);

        return response()->json(['message' => 'تم حذف الخدمة بنجاح']);
    }
}
