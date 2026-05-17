<?php

/**
 * وحدة التحكم الخاصة بتسجيل عملاء المتجر
 * تتعامل مع المسار العام لتسجيل المستخدمين بدون سياق شركة
 */

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Actions\User\RegisterMarketplaceCustomerAction;
use App\Http\Requests\Auth\MarketplaceRegisterRequest;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\JsonResponse;
use Throwable;

class MarketplaceRegisterController extends Controller
{
    /**
     * @group 01. إدارة المصادقة
     * 
     * تسجيل عميل متجر جديد
     * 
     * تسجيل مستخدم عالمي للمتجر بدون ربطه بأي شركة حالياً.
     */
    public function register(MarketplaceRegisterRequest $request, RegisterMarketplaceCustomerAction $action): JsonResponse
    {
        try {
            $user = $action->execute($request->validated());

            $token = $user->createToken('marketplace_token')->plainTextToken;

            return api_success([
                'user' => new UserResource($user),
                'token' => $token,
            ], 'تم تسجيل الحساب بنجاح كعميل متجر.', 201);

        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
