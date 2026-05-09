<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware لمنع تكرار العمليات المالية الحساسة.
 * يعتمد على الهيدر X-Idempotency-Key.
 */
class IdempotencyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Idempotency-Key');

        if (!$key) {
            return $next($request);
        }

        // استخدام معرف المستخدم مع المفتاح لضمان التفرد لكل مستخدم
        $cacheKey = "idempotency_" . ($request->user()?->id ?: $request->ip()) . "_" . $key;

        // التحقق من وجود استجابة سابقة مخزنة
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            return response()->json($cached['content'], $cached['status']);
        }

        $response = $next($request);

        // تخزين الاستجابات الناجحة فقط (200-299) لمدة 24 ساعة
        if ($response->isSuccessful()) {
            $content = json_decode($response->getContent(), true);
            Cache::put($cacheKey, [
                'content' => $content,
                'status' => $response->getStatusCode(),
            ], now()->addHours(24));
        }

        return $response;
    }
}
