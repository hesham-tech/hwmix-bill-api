<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;
class IdempotencyMiddleware {
    public function handle(Request $request,Closure $next): Response {
        $key=$request->header('X-Idempotency-Key');
        // These domains implement durable idempotency in their own database transactions.
        if(!$key||$request->isMethodSafe()||!$request->user()
            ||$request->is('api/v1/store/*','api/v1/payments/*','api/v1/vendor/store/*','api/v1/admin/store/*'))
            return $next($request);
        if(!preg_match('/^[a-zA-Z0-9_-]{1,128}$/D',$key))
            return response()->json(['message'=>'مفتاح تكرار الطلب غير صالح.'],422);
        $scope=[$request->user()->id,$request->user()->active_company_id,$request->user()->branch_id,
            $request->method(),$request->path(),$key];
        $cacheKey='idempotency:v2:'.hash('sha256',json_encode($scope));
        $fingerprint=hash('sha256',$request->getQueryString().'|'.$request->getContent());
        $lock=Cache::lock($cacheKey.':lock',120);
        if(!$lock->get()) return response()->json(['message'=>'تجري معالجة هذه المحاولة بالفعل.'],409);
        try {
            $cached=Cache::get($cacheKey);
            if($cached) {
                $cached=json_decode(Crypt::decryptString($cached),true,512,JSON_THROW_ON_ERROR);
                if(!hash_equals($cached['fingerprint'],$fingerprint))
                    return response()->json(['message'=>'لا يمكن إعادة استخدام المفتاح مع بيانات مختلفة.'],409);
                return response()->json($cached['content'],$cached['status']);
            }
            $response=$next($request);
            if($response->isSuccessful()&&str_contains((string)$response->headers->get('Content-Type'),'application/json')) {
                Cache::put($cacheKey,Crypt::encryptString(json_encode([
                    'fingerprint'=>$fingerprint,'content'=>json_decode($response->getContent(),true),
                    'status'=>$response->getStatusCode(),
                ],JSON_THROW_ON_ERROR)),now()->addHours(24));
            }
            return $response;
        } finally { $lock->release(); }
    }
}
