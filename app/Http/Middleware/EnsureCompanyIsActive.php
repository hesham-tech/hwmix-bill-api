<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\CurrentCompanyResolver;
use App\Models\Company;
use Illuminate\Support\Facades\Cache;

class EnsureCompanyIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = app(CurrentCompanyResolver::class)->resolve();

        if ($companyId) {
            $isActive = Cache::rememberForever('company_active_status_' . $companyId, function () use ($companyId) {
                $company = Company::withTrashed()->find($companyId);
                
                if (!$company) {
                    return false;
                }
                
                return !$company->trashed();
            });

            if (!$isActive) {
                return response()->json([
                    'success' => false,
                    'message' => 'عفواً، حساب الشركة الحالية تم تعليقه أو إرساله لسلة المحذوفات. يرجى تبديل الشركة من الواجهة أو التواصل مع الإدارة.',
                    'errors' => ['company_deleted' => true],
                ], 403);
            }
        }

        return $next($request);
    }
}
