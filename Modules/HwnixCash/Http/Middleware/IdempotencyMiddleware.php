<?php
// برمجية وسيطة لمنع تكرار معالجة الطلبات المتطابقة وتطبيق الـ Idempotency لكاش هونكس.

namespace Modules\HwnixCash\Http\Middleware;

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

        if (Cache::has($cacheKey)) {
            $cachedResponse = Cache::get($cacheKey);
            \Log::info("Idempotency match found for key: {$idempotencyKey}");
            
            return response()->json(
                $cachedResponse['content'],
                $cachedResponse['status'],
                ['X-Cache-Lookup' => 'HIT - Idempotent']
            );
        }

        $lockKey = 'idempotency_lock:' . $idempotencyKey;
        if (Cache::has($lockKey)) {
            return api_error('الطلب قيد المعالجة حالياً. الرجاء الانتظار.', [], 409);
        }

        Cache::put($lockKey, true, 30);

        $response = $next($request);

        Cache::forget($lockKey);

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
