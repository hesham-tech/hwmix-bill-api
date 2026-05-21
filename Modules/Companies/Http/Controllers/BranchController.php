<?php

namespace Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Companies\Models\Branch;
use Modules\Companies\Http\Requests\StoreBranchRequest;
use Modules\Companies\Http\Requests\UpdateBranchRequest;
use Modules\Companies\Transformers\BranchResource;
use Illuminate\Support\Facades\Auth;

/**
 * متحكم لإدارة الفروع (Branches) داخل الشركة.
 * يتبع قواعد الـ Multi-tenant بحيث يرى كل مدير فروع شركته فقط.
 */
class BranchController extends Controller
{
    /**
     * عرض قائمة الفروع للشركة الحالية.
     */
    public function index(): JsonResponse
    {
        $branches = Branch::whereCompanyIsCurrent()->get();
        return api_success(BranchResource::collection($branches), 'تم جلب الفروع بنجاح.');
    }

    /**
     * إنشاء فرع جديد.
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['company_id'] = Auth::user()->active_company_id;

        // إذا كان الفرع افتراضياً، قم بإزالة الافتراضية من الفروع الأخرى
        if ($validated['is_default'] ?? false) {
            Branch::where('company_id', $validated['company_id'])->update(['is_default' => false]);
        }

        $branch = Branch::create($validated);
        return api_success(new BranchResource($branch), 'تم إنشاء الفرع بنجاح.', 201);
    }

    /**
     * عرض تفاصيل فرع محدد.
     */
    public function show(Branch $branch): JsonResponse
    {
        $this->authorizeAccess($branch);
        return api_success(new BranchResource($branch), 'تم جلب بيانات الفرع.');
    }

    /**
     * تحديث بيانات الفرع.
     */
    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $this->authorizeAccess($branch);
        $validated = $request->validated();

        if ($validated['is_default'] ?? false) {
            Branch::where('company_id', $branch->company_id)->update(['is_default' => false]);
        }

        $branch->update($validated);
        return api_success(new BranchResource($branch), 'تم تحديث بيانات الفرع بنجاح.');
    }

    /**
     * حذف الفرع.
     */
    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorizeAccess($branch);

        if ($branch->is_default) {
            return api_error('لا يمكن حذف الفرع الافتراضي للشركة.', [], 422);
        }

        $branch->delete();
        return api_success(null, 'تم حذف الفرع بنجاح.');
    }

    /**
     * إسناد مستخدمين لفرع محدد.
     */
    public function assignUsers(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeAccess($branch);
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $branch->users()->syncWithoutDetaching($request->user_ids);
        return api_success(null, 'تم إسناد المستخدمين للفرع بنجاح.');
    }

    /**
     * إزالة مستخدمين من فرع محدد.
     */
    public function removeUsers(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeAccess($branch);
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $branch->users()->detach($request->user_ids);
        return api_success(null, 'تم إزالة المستخدمين من الفرع بنجاح.');
    }

    /**
     * دالة خاصة للتحقق من أن الفرع ينتمي لنفس شركة المستخدم الحالي.
     */
    protected function authorizeAccess(Branch $branch)
    {
        if ($branch->company_id !== Auth::user()->active_company_id && !Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
            abort(403, 'غير مصرح لك بالوصول لهذا الفرع.');
        }
    }
}
