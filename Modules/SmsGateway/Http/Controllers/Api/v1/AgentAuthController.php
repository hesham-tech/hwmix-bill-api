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
     * تسجيل مستخدم جديد من التطبيق بدون صلاحيات.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'nickname' => 'required|string|max:255',
            'phone' => 'required|string|unique:users,phone',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:6',
            'device_uuid' => 'required|string',
        ]);

        $company = \App\Models\Company::first();
        $companyId = $company ? $company->id : 1;

        $user = User::create([
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'company_id' => $companyId,
            'full_name' => $validated['full_name'],
            'nickname' => $validated['nickname'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->companies()->attach($companyId, [
            'created_by' => $user->id,
            'user_phone' => $user->phone,
            'full_name_in_company' => $user->full_name,
            'nickname_in_company' => $user->nickname,
        ]);

        $tokenName = 'SMS_Gateway_Agent_' . $validated['device_uuid'];
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
                'id' => $user->company_id,
            ]
        ], 'تم إنشاء الحساب بنجاح وتوليد رمز الوصول.', 201);
    }

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
        if (!$user) {
            return api_error('البريد الإلكتروني أو الهاتف غير مسجل لدينا.', [], 421);
        }

        if (!Hash::check($validated['password'], $user->password)) {
            return api_error('كلمة المرور غير صحيحة.', [], 421);
        }

        // تسجيل الدخول
        if (!Auth::attempt([$loginField => $validated['login'], 'password' => $validated['password']])) {
            return api_error('فشل عملية تسجيل الدخول.', [], 421);
        }

        /** @var User $user */
        $user = Auth::user();

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
                'id' => $user->company_id,
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
