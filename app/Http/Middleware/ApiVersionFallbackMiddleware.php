<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * تحويل الروابط التي لا تحتوي على إصدار api/v1 إلى api/v1 تلقائياً للتوافق العكسي وتجنب أخطاء 404 في الاختبارات والخدمات القديمة.
 */
class ApiVersionFallbackMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $uri = $request->getRequestUri();

        // التحقق مما إذا كان الرابط يبدأ بـ /api/ ولا يحتوي على /api/v1/
        if (str_starts_with($uri, '/api/') && !str_starts_with($uri, '/api/v1/')) {
            // استبعاد مسارات معينة مثل تقارير الأخطاء العامة
            if (!str_starts_with($uri, '/api/error-reports')) {
                // إعادة كتابة REQUEST_URI عن طريق إدراج v1/ بعد /api/
                $newUri = '/api/v1/' . substr($uri, 5);

                $request->server->set('REQUEST_URI', $newUri);

                // إعادة تهيئة الطلب لتحديث الـ PathInfo والـ Query بشكل صحيح
                $request->initialize(
                    $request->query->all(),
                    $request->request->all(),
                    $request->attributes->all(),
                    $request->cookies->all(),
                    $request->files->all(),
                    $request->server->all(),
                    $request->getContent()
                );
            }
        }

        return $next($request);
    }
}
