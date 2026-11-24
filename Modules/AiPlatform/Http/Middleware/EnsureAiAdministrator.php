<?php
namespace Modules\AiPlatform\Http\Middleware;

use Closure;
use Illuminate\Http\{JsonResponse, Request};
use Symfony\Component\HttpFoundation\Response;

final class EnsureAiAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) return new JsonResponse(['success' => false, 'message' => 'يجب تسجيل الدخول.'], 401);
        if (!$user->checkPermissionTo(perm_key('admin.super'))) {
            return new JsonResponse(['success' => false, 'message' => 'إدارة منصة الذكاء الاصطناعي متاحة لمدير النظام فقط.'], 403);
        }
        // Caller input never selects another tenant, even for this administrative API.
        if ($request->exists('company_id')) {
            $id = $request->input('company_id');
            if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id <= 0
                || (int) $id !== (int) $user->active_company_id) {
                return new JsonResponse(['success' => false, 'message' => 'استخدم سياق الشركة النشطة المصرح به.'], 403);
            }
        }
        if ($request->exists('is_system') && !in_array($request->input('is_system'), [true, false, 0, 1, '0', '1'], true)) {
            return new JsonResponse(['success' => false, 'message' => 'نطاق النظام غير صالح.'], 422);
        }
        return $next($request);
    }
}
