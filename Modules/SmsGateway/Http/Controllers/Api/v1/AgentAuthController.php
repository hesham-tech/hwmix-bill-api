<?php
// متحكم مصادقة تطبيق الأندرويد وإصدار رموز الوصول وإدارتها.

namespace Modules\SmsGateway\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AgentAuthController extends Controller
{
    /**
     * تسجيل دخول الـ Agent وتوليد Token مخصص.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
            'device_uuid' => 'required|string',
        ]);

        $loginField = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = User::where($loginField, $validated['login'])->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return api_error('بيانات الاعتماد غير صالحة.', [], 421);
        }

        // تسجيل الدخول
        if (!Auth::attempt([$loginField => $validated['login'], 'password' => $validated['password']])) {
            return api_error('فشل عملية تسجيل الدخول.', [], 421);
        }

        /** @var User $user */
        $user = Auth::user();

        if (!$user->active_company_id) {
            return api_error('المستخدم غير مرتبط بشركة نشطة حالياً.', [], 403);
        }

        // توليد Token مخصص للـ Agent وصالح لمدة شهر
        $tokenName = 'SMS_Gateway_Agent_' . $validated['device_uuid'];
        
        // إزالة أي توكن قديم بنفس الاسم للجهاز لتنظيف الجداول
        $user->tokens()->where('name', $tokenName)->delete();

        $token = $user->createToken($tokenName, ['*'], now()->addDays(30))->plainTextToken;

        return api_success([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'company' => [
                'id' => $user->active_company_id,
            ]
        ], 'تمت المصادقة بنجاح.');
    }

    /**
     * تجديد صلاحية التوكن للـ Agent.
     */
    public function refresh(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        
        if (!$user) {
            return api_error('غير مصرح بالعملية.', [], 401);
        }

        $validated = $request->validate([
            'device_uuid' => 'required|string',
        ]);

        // حذف التوكن الحالي
        $request->user()->currentAccessToken()->delete();

        // إنشاء توكن جديد
        $tokenName = 'SMS_Gateway_Agent_' . $validated['device_uuid'];
        $newToken = $user->createToken($tokenName, ['*'], now()->addDays(30))->plainTextToken;

        return api_success([
            'token' => $newToken,
        ], 'تم تجديد الرمز بنجاح.');
    }
}
