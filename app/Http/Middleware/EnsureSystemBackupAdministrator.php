<?php
namespace App\Http\Middleware;

use App\Policies\SystemBackupPolicy;
use Closure;
use Illuminate\Http\{JsonResponse, Request};
use Symfony\Component\HttpFoundation\Response;

final class EnsureSystemBackupAdministrator
{
    public function __construct(private SystemBackupPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) return new JsonResponse(['status' => false, 'message' => 'يجب تسجيل الدخول.'], 401);
        if (!$this->policy->manage($user)) {
            return new JsonResponse(['status' => false, 'message' => 'إدارة نسخ النظام متاحة لمدير النظام فقط.'], 403);
        }
        return $next($request);
    }
}
