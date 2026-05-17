<?php

/**
 * وحدة التحكم الخاصة بتجهيز المستأجرين (SaaS Registration)
 * تتعامل مع المسار العام لتسجيل الشركات الجديدة في النظام
 */

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Actions\Company\ProvisionNewCompanyAction;
use App\Http\Requests\Auth\ProvisionNewCompanyRequest;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\JsonResponse;
use Throwable;

class TenantProvisioningController extends Controller
{
    /**
     * @group 01. إدارة المصادقة
     * 
     * تسجيل شركة جديدة (SaaS)
     * 
     * إنشاء مستأجر جديد في النظام مع حساب المالك وكافة الإعدادات الافتراضية.
     */
    public function register(ProvisionNewCompanyRequest $request, ProvisionNewCompanyAction $action): JsonResponse
    {
        try {
            $result = $action->execute($request->validated());

            $user = $result['user'];
            $token = $user->createToken('saas_token')->plainTextToken;

            return api_success([
                'company' => $result['company'],
                'user' => new UserResource($user),
                'token' => $token,
            ], 'تم إنشاء الشركة وتجهيز حساب المدير بنجاح.', 201);

        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
