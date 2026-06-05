<?php

namespace App\Http\Middleware;

// تعليق عربي: وسيط برمجية (Middleware) للتحقق من عدم تجاوز الشركة المشتركة للحد الأقصى المسموح به في الباقة قبل إنشاء أي مورد جديد.

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\SaaS\LimitResolver;
use Symfony\Component\HttpFoundation\Response;

class CheckSaaSResourceLimit
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $user = Auth::user();
        if (!$user) {
            return api_unauthorized('المستخدم غير مصادق عليه.');
        }

        $companyId = $user->active_company_id;

        // السوبر أدمن معفى تماماً من قيود الباقات والموارد
        if ($user->hasPermissionTo(perm_key('admin.super'))) {
            return $next($request);
        }

        // التحقق من قيود الباقة الجارية
        if (!LimitResolver::isWithinLimit($companyId, $resource)) {
            $resourceLabel = $this->getResourceLabel($resource);
            return response()->json([
                'status' => false,
                'message' => "لقد تجاوزت الحد الأقصى المسموح به لإنشاء {$resourceLabel} في باقتك الحالية. يرجى الترقية للاستمرار.",
                'error_code' => 'SUBSCRIPTION_LIMIT_REACHED',
                'errors' => ['resource' => $resource],
            ], 403);
        }

        return $next($request);
    }

    /**
     * ترجمة اسم المورد للغة العربية لعرض رسالة واضحة للمستخدم.
     */
    protected function getResourceLabel(string $resource): string
    {
        try {
            $metric = \Illuminate\Support\Facades\DB::table('usage_metrics')
                ->where('key', $resource)
                ->first();
            if ($metric) {
                return "[{$metric->name}]";
            }
        } catch (\Throwable $e) {}

        return match ($resource) {
            'users' => '[الموظفين والمستخدمين]',
            'products' => '[المنتجات]',
            'invoices' => '[الفواتير]',
            'warehouses' => '[المخازن]',
            default => "[{$resource}]",
        };
    }
}
