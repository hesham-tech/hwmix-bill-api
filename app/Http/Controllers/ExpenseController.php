<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\Expense\ExpenseResource;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = Expense::with(['category', 'creator'])
            ->when($request->search, function ($q) use ($request) {
                return $q->where('reference_number', 'like', "%{$request->search}%")
                    ->orWhere('notes', 'like', "%{$request->search}%");
            })
            ->when($request->expense_category_id, function ($q) use ($request) {
                return $q->where('expense_category_id', $request->expense_category_id);
            })
            ->when($request->date_from, function ($q) use ($request) {
                return $q->whereDate('expense_date', '>=', $request->date_from);
            })
            ->when($request->date_to, function ($q) use ($request) {
                return $q->whereDate('expense_date', '<=', $request->date_to);
            });

        // تطبيق منطق الصلاحيات
        if ($user->hasPermissionTo(perm_key('admin.super'))) {
            // المسؤول العام يرى كل شيء
        } elseif ($user->hasAnyPermission([perm_key('expenses.view_all'), perm_key('admin.company')])) {
            $query->whereCompanyIsCurrent();
        } elseif ($user->hasPermissionTo(perm_key('expenses.view_children'))) {
            $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
        } elseif ($user->hasPermissionTo(perm_key('expenses.view_self'))) {
            $query->whereCompanyIsCurrent()->whereCreatedByUser();
        } else {
            return api_forbidden('ليس لديك إذن لعرض المصاريف.');
        }

        $expenses = $query->latest('expense_date')->paginate($request->per_page ?? 15);

        return api_success(ExpenseResource::collection($expenses));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'notes' => 'nullable|string',
            'reference_number' => 'nullable|string|max:100',
        ]);

        $expense = Expense::create($request->all());

        return api_success(new ExpenseResource($expense->load(['category', 'creator'])), 'تم تسجيل المصروف بنجاح');
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        // Permission Check
        $user = auth()->user();
        if ($user->hasPermissionTo(perm_key('admin.super'))) {
            // Authorized
        } elseif ($user->hasAnyPermission([perm_key('expenses.update_all'), perm_key('admin.company')])) {
            if (!$expense->belongsToCurrentCompany())
                return api_forbidden();
        } elseif ($user->hasPermissionTo(perm_key('expenses.update_self'))) {
            if (!$expense->createdByCurrentUser())
                return api_forbidden();
        } else {
            return api_forbidden('ليس لديك صلاحية لتعديل هذا المصروف');
        }

        $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'notes' => 'nullable|string',
            'reference_number' => 'nullable|string|max:100',
        ]);

        $expense->update($request->all());

        return api_success(new ExpenseResource($expense->load(['category', 'creator'])), 'تم تحديث المصروف بنجاح');
    }

    public function destroy(Expense $expense): JsonResponse
    {
        // Permission Check
        $user = auth()->user();
        if ($user->hasPermissionTo(perm_key('admin.super'))) {
            // Authorized
        } elseif ($user->hasAnyPermission([perm_key('expenses.delete_all'), perm_key('admin.company')])) {
            if (!$expense->belongsToCurrentCompany())
                return api_forbidden();
        } elseif ($user->hasPermissionTo(perm_key('expenses.delete_self'))) {
            if (!$expense->createdByCurrentUser())
                return api_forbidden();
        } else {
            return api_forbidden('ليس لديك صلاحية لحذف هذا المصروف');
        }

        $expense->delete();

        return api_success(null, 'تم حذف المصروف بنجاح');
    }

    public function getSummary(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = Expense::query();

        // تطبيق منطق الصلاحيات
        if ($user->hasPermissionTo(perm_key('admin.super'))) {
            // المسؤول العام يرى كل شيء
        } elseif ($user->hasAnyPermission([perm_key('expenses.view_all'), perm_key('admin.company')])) {
            $query->whereCompanyIsCurrent();
        } elseif ($user->hasPermissionTo(perm_key('expenses.view_children'))) {
            $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
        } else {
            $query->whereCompanyIsCurrent()->whereCreatedByUser();
        }

        $total = $query->when($request->date_from, function ($q) use ($request) {
            return $q->whereDate('expense_date', '>=', $request->date_from);
        })->when($request->date_to, function ($q) use ($request) {
            return $q->whereDate('expense_date', '<=', $request->date_to);
        })->sum('amount');

        return api_success(['total_amount' => $total]);
    }
}
