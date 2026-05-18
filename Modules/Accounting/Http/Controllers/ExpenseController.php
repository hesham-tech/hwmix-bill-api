<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Accounting\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Accounting\Http\Resources\ExpenseResource;

/**
 * متحكم المصاريف (ExpenseController) - موديول المحاسبة
 */
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
            });

        if ($user->hasPermissionTo(perm_key('admin.super'))) {
        } elseif ($user->hasAnyPermission([perm_key('expenses.view_all'), perm_key('admin.company')])) {
            $query->where('company_id', $user->active_company_id);
        } else {
            $query->where('created_by', $user->id);
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
        ]);

        $expense = Expense::create($request->all());
        return api_success(new ExpenseResource($expense->load(['category', 'creator'])), 'تم تسجيل المصروف بنجاح');
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $user = auth()->user();
        if (!$user->hasPermissionTo(perm_key('admin.super')) && $expense->company_id !== $user->active_company_id) {
            return api_forbidden();
        }

        $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
        ]);

        $expense->update($request->all());
        return api_success(new ExpenseResource($expense->load(['category', 'creator'])), 'تم تحديث المصروف بنجاح');
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $user = auth()->user();
        if (!$user->hasPermissionTo(perm_key('admin.super')) && $expense->company_id !== $user->active_company_id) {
            return api_forbidden();
        }

        $expense->delete();
        return api_success(null, 'تم حذف المصروف بنجاح');
    }

    public function getSummary(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = Expense::query();

        if (!$user->hasPermissionTo(perm_key('admin.super'))) {
            $query->where('company_id', $user->active_company_id);
        }

        $total = $query->sum('amount');
        return api_success(['total_amount' => $total]);
    }
}
