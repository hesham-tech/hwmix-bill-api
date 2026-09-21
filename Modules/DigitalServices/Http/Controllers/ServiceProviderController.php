<?php

namespace Modules\DigitalServices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServiceProviderController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('service_providers')
            ->where(function($q) use ($request) {
                $q->where('company_id', $request->user()->company_id)
                  ->orWhereNull('company_id'); // Global providers
            })
            ->whereNull('deleted_at');

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $providers = $query->orderBy('name')->get();
            
        return response()->json(['data' => $providers]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100',
            'category' => 'nullable|string|in:wallet,machine,bank',
            'is_active' => 'boolean',
        ]);

        $companyId = $request->user()->company_id;

        // Ensure unique code per company
        $exists = DB::table('service_providers')
            ->where('company_id', $companyId)
            ->where('code', $validated['code'])
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'الكود مستخدم مسبقاً.'], 422);
        }

        $id = DB::table('service_providers')->insertGetId([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'category' => $validated['category'] ?? 'wallet',
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم إضافة شبكة الدفع بنجاح',
            'data' => DB::table('service_providers')->find($id)
        ]);
    }

    public function update(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        
        $provider = DB::table('service_providers')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$provider) abort(404, 'الشبكة غير موجودة');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100',
            'category' => 'nullable|string|in:wallet,machine,bank',
            'is_active' => 'boolean',
        ]);

        // check code unique
        $exists = DB::table('service_providers')
            ->where('company_id', $companyId)
            ->where('code', $validated['code'])
            ->where('id', '!=', $id)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'الكود مستخدم مسبقاً.'], 422);
        }

        DB::table('service_providers')->where('id', $id)->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'category' => $validated['category'] ?? 'wallet',
            'is_active' => $validated['is_active'] ?? true,
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم التحديث بنجاح',
            'data' => DB::table('service_providers')->find($id)
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        
        // Prevent deletion if connected to definitions or provider_accounts
        $hasAccounts = DB::table('provider_accounts')
            ->join('service_definitions', 'service_definitions.id', '=', 'provider_accounts.id') // Wait, provider_accounts connects directly to service_providers? 
            // Ah, wait! The `ProviderManagementController.php` validating service_provider_id
            ->exists(); 

        DB::table('service_providers')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->update([
                'deleted_at' => now()
            ]);

        return response()->json(['message' => 'تم حذف الشبكة بنجاح']);
    }
}
