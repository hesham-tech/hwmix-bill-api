<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\ExpenseCategory\ExpenseCategoryResource;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = ExpenseCategory::query();

        // تطبيق منطق الصلاحيات
        if ($user->hasPermissionTo(perm_key('admin.super'))) {
            // المسؤول العام يرى كل شيء
        } elseif ($user->hasAnyPermission([perm_key('expense_categories.view_all'), perm_key('admin.company')])) {
            $query->whereCompanyIsCurrent();
        } else {
            $query->whereCompanyIsCurrent(); // التصنيفات عادة تكون للشركة بالكامل
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
            'name' => 'required|string|max:255|unique:expense_categories,name,NULL,id,company_id,' . auth()->user()->company_id,
        ]);

        $category = ExpenseCategory::create($request->all());

        return api_success(new ExpenseCategoryResource($category), 'تم إضافة التصنيف بنجاح');
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:expense_categories,name,' . $expenseCategory->id . ',id,company_id,' . auth()->user()->company_id,
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
