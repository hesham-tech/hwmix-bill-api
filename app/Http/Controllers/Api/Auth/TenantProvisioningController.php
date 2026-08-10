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
            $validated = $request->validated();

            // فحص هل المستخدم موجود مسبقاً في النظام
            $user = \App\Models\User::withoutGlobalScopes()
                ->where(function ($query) use ($validated) {
                    $query->where('phone', $validated['phone']);
                    if (!empty($validated['email'])) {
                        $query->orWhere('email', $validated['email']);
                    }
                })->first();

            if ($user) {
                // إذا كان موجوداً، نفحص تطابق كلمة المرور التي أدخلها
                if (!\Illuminate\Support\Facades\Hash::check($validated['password'], $user->password)) {
                    $maskedEmail = null;
                    if (!empty($user->email)) {
                        $parts = explode('@', $user->email);
                        if (count($parts) === 2) {
                            $name = $parts[0];
                            $domain = $parts[1];
                            $maskedName = substr($name, 0, 1) . str_repeat('*', max(1, strlen($name) - 1));
                            $maskedEmail = $maskedName . '@' . $domain;
                        }
                    }

                    return response()->json([
                        'status' => false,
                        'message' => 'لديك حساب عميل علي إحدي الشركات علي السيستم.',
                        'data' => [
                            'user_exists' => true,
                            'has_email' => !empty($user->email),
                            'masked_email' => $maskedEmail
                        ]
                    ], 409);
                }
            }

            $result = $action->execute($validated);
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
