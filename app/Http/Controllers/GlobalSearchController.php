<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\CompanyUser;
use App\Models\Invoice;
use App\Models\InstallmentPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GlobalSearchController extends Controller
{
    /**
     * Search across multiple entities.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $query = $request->get('query');

        if (empty($query)) {
            return response()->json([
                'users' => [],
                'invoices' => [],
                'installment_plans' => [],
            ]);
        }

        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();
        $companyId = $authUser->company_id;

        // 1. Search Users (Customers)
        if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            // Super Admin: Search all unique users globally
            $users = User::query()
                ->smartSearch($query, ['full_name', 'nickname', 'phone', 'email', 'username'])
                ->with([
                    'image',
                    'cashBoxes' => function ($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                ])
                ->limit(10)
                ->get()
                ->append('balance')
                ->map(fn($u) => [
                    'id' => $u->id,
                    'full_name' => $u->full_name,
                    'nickname' => $u->nickname,
                    'phone' => $u->phone,
                    'balance' => $u->balance,
                    'avatar_url' => $u->avatar_url,
                ]);
        } else {
            // Managers/Staff: Search users linked to the active company via company_user
            // This ensures results reflect the user's specific identity and balance in this company
            $users = CompanyUser::query()
                ->where('company_id', $companyId)
                ->smartSearch($query, ['nickname_in_company', 'full_name_in_company'], [
                    'user' => ['full_name', 'nickname', 'phone', 'email', 'username']
                ])
                ->with([
                    'user',
                    'user.image',
                    'user.cashBoxes' => function ($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                ])
                ->limit(10)
                ->get()
                ->map(fn($cu) => [
                    'id' => $cu->user_id,
                    'full_name' => $cu->full_name,
                    'nickname' => $cu->nickname,
                    'phone' => $cu->phone,
                    'balance' => $cu->balance,
                    'avatar_url' => $cu->user?->avatar_url,
                ]);
        }

        // 2. Search Invoices
        $invoicesQuery = Invoice::query();

        if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            // No restrictions
        } elseif ($authUser->hasAnyPermission([perm_key('invoices.view_all'), perm_key('admin.company')])) {
            $invoicesQuery->whereCompanyIsCurrent();
        } elseif ($authUser->hasPermissionTo(perm_key('invoices.view_children'))) {
            $invoicesQuery->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
        } elseif ($authUser->hasPermissionTo(perm_key('invoices.view_self'))) {
            $invoicesQuery->whereCompanyIsCurrent()->whereCreatedByUser();
        } else {
            // Default: Own invoices
            $invoicesQuery->where('user_id', $authUser->id);
        }

        $invoices = $invoicesQuery->smartSearch($query, ['invoice_number'])
            ->with(['user:id,full_name'])
            ->limit(10)
            ->get(['id', 'invoice_number', 'net_amount as total_amount', 'user_id', 'issue_date', 'status']);

        // 3. Search Installment Plans
        $plansQuery = InstallmentPlan::query();

        if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            // No restrictions
        } elseif ($authUser->hasAnyPermission([perm_key('installment_plans.view_all'), perm_key('admin.company')])) {
            $plansQuery->whereCompanyIsCurrent();
        } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.view_children'))) {
            $plansQuery->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
        } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.view_self'))) {
            $plansQuery->whereCompanyIsCurrent()->whereCreatedByUser();
        } else {
            // Default: Own plans
            $plansQuery->where('user_id', $authUser->id);
        }

        $plans = $plansQuery->smartSearch($query, [], [
            'customer' => ['full_name', 'phone'],
            'invoice' => ['invoice_number']
        ])
            ->with(['customer:id,full_name'])
            ->limit(10)
            ->get(['id', 'user_id', 'total_amount', 'status', 'start_date']);

        return response()->json([
            'users' => $users,
            'invoices' => $invoices,
            'installment_plans' => $plans,
        ]);
    }
}
