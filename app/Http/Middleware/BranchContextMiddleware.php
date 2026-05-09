<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

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
            
            // TODO: هنا يمكن إضافة تحقق ما إذا كان للمستخدم صلاحية الوصول لهذا الفرع
            
            if ($headerBranchId) {
                config(['app.active_branch_id' => $headerBranchId]);
            } else {
                // 2. استخدام فرع المستخدم الافتراضي إذا لم يحدد هيدر
                config(['app.active_branch_id' => $user->branch_id]);
            }
        }

        return $next($request);
    }
}
