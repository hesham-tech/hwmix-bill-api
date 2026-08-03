<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * ميدل وير للتحقق من صلاحية المستخدم للوصول إلى الشركة المرسلة في هيدر X-HWNIX-COMPANY من الموبايل.
 */
class MobileCompanyContextMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasHeader('X-HWNIX-COMPANY')) {
            $companyId = $request->header('X-HWNIX-COMPANY');
            $user = Auth::user();

            if ($user && $companyId) {
                $hasAccess = true;

                // إذا لم يكن سوبر أدمن، تحقق من ارتباطه بالشركة
                if (!$user->hasPermissionTo(perm_key('admin.super'))) {
                    // التحقق من أن المستخدم مرتبط بالشركة عبر جدول company_user أو كشركة نشطة
                    $isAssociated = \DB::table('company_user')
                        ->where('user_id', $user->id)
                        ->where('company_id', $companyId)
                        ->exists();

                    if (!$isAssociated && $user->active_company_id != $companyId) {
                        $hasAccess = false;
                    }
                }

                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'message' => 'ليس لديك صلاحية للوصول إلى بيانات هذه الشركة.',
                        'error' => 'Forbidden'
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
