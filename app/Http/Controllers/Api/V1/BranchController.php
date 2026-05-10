<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group إدارة الفروع
 * إدارة فروع الشركة (عرض، إضافة، تعديل، حذف)
 */
class BranchController extends Controller
{
    /**
     * عرض جميع الفروع
     */
    public function index()
    {
        $branches = Branch::all();
        return response()->json([
            'status' => true,
            'data' => $branches
        ]);
    }

    /**
     * عرض الفروع المسموح للمستخدم الحالي رؤيتها
     */
    public function myBranches()
    {
        $user = auth()->user();
        if ($user->hasPermissionTo(perm_key('admin.company'))) {
            // مدير الشركة يرى جميع فروع شركته
            $branches = Branch::all();
        } else {
            // الموظف العادي يرى الفروع المخصصة له فقط
            $allowedIds = $user->getAllowedBranchIds();
            $branches = Branch::whereIn('id', $allowedIds)->get();
        }

        return response()->json([
            'status' => true,
            'data' => $branches
        ]);
    }

    /**
     * إنشاء فرع جديد
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'is_default' => 'nullable|boolean'
        ]);

        try {
            DB::beginTransaction();

            // إذا كان هذا الفرع هو الافتراضي، نلغي الافتراضي عن الباقي
            if ($request->is_default) {
                Branch::where('company_id', auth()->user()->company_id)->update(['is_default' => false]);
            }

            $branch = Branch::create([
                'name' => $request->name,
                'address' => $request->address,
                'phone' => $request->phone,
                'email' => $request->email,
                'is_default' => $request->is_default ?? false,
                'company_id' => auth()->user()->company_id,
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'تم إنشاء الفرع بنجاح',
                'data' => $branch
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء إنشاء الفرع: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * عرض تفاصيل فرع
     */
    public function show(Branch $branch)
    {
        return response()->json([
            'status' => true,
            'data' => $branch
        ]);
    }

    /**
     * تحديث بيانات الفرع
     */
    public function update(Request $request, Branch $branch)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'is_default' => 'nullable|boolean'
        ]);

        try {
            DB::beginTransaction();

            if ($request->is_default && !$branch->is_default) {
                Branch::where('company_id', auth()->user()->company_id)->update(['is_default' => false]);
            }

            $branch->update([
                'name' => $request->name,
                'address' => $request->address,
                'phone' => $request->phone,
                'email' => $request->email,
                'is_default' => $request->is_default ?? false,
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'تم تحديث بيانات الفرع بنجاح',
                'data' => $branch
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء تحديث الفرع'
            ], 500);
        }
    }

    /**
     * حذف فرع
     */
    public function destroy(Branch $branch)
    {
        if ($branch->is_default) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن حذف الفرع الافتراضي للشركة'
            ], 422);
        }

        $branch->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف الفرع بنجاح'
        ]);
    }
}
