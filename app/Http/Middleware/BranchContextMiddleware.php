<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Modules\Companies\Models\Branch;

/**
 * ميدل وير لتحديد سياق الفرع النشط (Active Branch Context).
 */
class BranchContextMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user) {
            // 1. التحقق من الهيدر (للمدراء الذين يتنقلون بين الفروع)
            $headerBranchId = $request->header('X-Branch-Id');

            if ($headerBranchId && $headerBranchId !== 'all') {
                // التحقق الأمني: التأكد أن الفرع المُرسل ينتمي لشركة المستخدم النشطة
                // هذا يمنع تعارض السياق عند تغيير الشركة من جهاز آخر
                $activeCompanyId = $user->active_company_id;

                $branchBelongsToCompany = $activeCompanyId && Branch::where('id', $headerBranchId)
                    ->where('company_id', $activeCompanyId)
                    ->exists();

                if ($branchBelongsToCompany) {
                    config(['app.active_branch_id' => $headerBranchId]);
                } else {
                    // الفرع لا ينتمي للشركة النشطة → تجاهله والرجوع للفرع الافتراضي
                    config(['app.active_branch_id' => $user->branch_id]);
                }
            } elseif ($headerBranchId === 'all') {
                // طلب عرض كل الفروع (مسموح للمدراء)
                config(['app.active_branch_id' => 'all']);
            } else {
                // 2. استخدام فرع المستخدم الافتراضي إذا لم يحدد هيدر
                config(['app.active_branch_id' => $user->branch_id]);
            }
        }

        return $next($request);
    }
}

