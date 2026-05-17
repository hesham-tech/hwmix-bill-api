<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Accounting\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Accounting\Http\Resources\ExpenseCategoryResource;

/**
 * متحكم تصنيفات المصاريف (ExpenseCategoryController) - موديول المحاسبة
 */
class ExpenseCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = ExpenseCategory::query();

        if (!$user->hasPermissionTo(perm_key('admin.super'))) {
            $query->where('company_id', $user->company_id);
        }

        $categories = $query->when($request->all, function ($q) {
            return $q->get();
        }, function ($q) use ($request) {
            return $q->paginate($request->per_page ?? 15);
        });

        return api_success(ExpenseCategoryResource::collection($categories));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category = ExpenseCategory::create($request->all());
        return api_success(new ExpenseCategoryResource($category), 'تم إضافة التصنيف بنجاح');
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $expenseCategory->update($request->all());
        return api_success(new ExpenseCategoryResource($expenseCategory), 'تم تحديث التصنيف بنجاح');
    }

    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        if ($expenseCategory->expenses()->count() > 0) {
            return api_error('لا يمكن حذف التصنيف لأنه مرتبط بمصاريف مسجلة');
        }

        $expenseCategory->delete();
        return api_success(null, 'تم حذف التصنيف بنجاح');
    }
}
