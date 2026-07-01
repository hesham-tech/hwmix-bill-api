<?php
// برمجية وسيطة (Middleware) لمنع تكرار معالجة الطلبات المتطابقة وتطبيق الـ Idempotency.

namespace Modules\SmsGateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class IdempotencyMiddleware
{
    /**
     * معالجة الطلب الوارد وتدقيق مفتاح منع التكرار.
     */
    public function handle(Request $request, Closure $next)
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$idempotencyKey) {
            return $next($request);
        }

        $cacheKey = 'idempotency:' . $idempotencyKey;

        // التحقق من معالجة هذا المفتاح سابقاً
        if (Cache::has($cacheKey)) {
            $cachedResponse = Cache::get($cacheKey);
            \Log::info("Idempotency match found for key: {$idempotencyKey}");
            
            return response()->json(
                $cachedResponse['content'],
                $cachedResponse['status'],
                ['X-Cache-Lookup' => 'HIT - Idempotent']
            );
        }

        // إذا كان المفتاح قيد المعالجة حالياً لتجنب Race Conditions
        $lockKey = 'idempotency_lock:' . $idempotencyKey;
        if (Cache::has($lockKey)) {
            return api_error('الطلب قيد المعالجة حالياً. الرجاء الانتظار.', [], 409);
        }

        // قفل المفتاح لـ 30 ثانية أثناء المعالجة
        Cache::put($lockKey, true, 30);

        // تنفيذ الطلب والحصول على الاستجابة
        $response = $next($request);

        // فك قفل المعالجة
        Cache::forget($lockKey);

        // تخزين استجابة الطلب الناجح (200 أو 201) لـ 24 ساعة
        if ($response->isSuccessful()) {
            $dataToCache = [
                'content' => json_decode($response->getContent(), true),
                'status' => $response->getStatusCode(),
            ];
            Cache::put($cacheKey, $dataToCache, now()->addHours(24));
        }

        return $response;
    }
}
