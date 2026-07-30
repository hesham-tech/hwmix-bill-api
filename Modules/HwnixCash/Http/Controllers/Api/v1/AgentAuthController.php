<?php
// متحكم مصادقة تطبيق الأندرويد وإصدار رموز الوصول وإدارتها لكاش هونكس HwnixCash.

namespace Modules\HwnixCash\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\HwnixCash\Http\Requests\Agent\LoginAgentRequest;
use Modules\HwnixCash\Http\Requests\Agent\RefreshAgentRequest;
use Modules\HwnixCash\Http\Requests\Agent\RegisterAgentRequest;

use App\Actions\Company\ProvisionNewCompanyAction;

class AgentAuthController extends Controller
{
    /**
     * تسجيل شركة جديدة ومستخدم مدير من تطبيق الأندرويد.
     */
    public function register(RegisterAgentRequest $request, ProvisionNewCompanyAction $provisionAction): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $result = $provisionAction->execute([
                'company_name' => $validated['company_name'],
                'full_name' => $validated['full_name'],
                'nickname' => $validated['nickname'] ?? $validated['full_name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'password' => $validated['password'],
            ]);

            $user = $result['user'];
            $company = $result['company'];

            $tokenName = 'Hwnix_Cash_Agent_' . $validated['device_uuid'];
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
                    'id' => $company->id,
                    'name' => $company->name,
                ]
            ], 'تم إنشاء الشركة وتجهيز حساب المدير بنجاح.', 201);

        } catch (\Exception $e) {
            return api_error('حدث خطأ في السيرفر أثناء إنشاء الشركة: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * تسجيل دخول الـ Agent وتوليد Token مخصص.
     */
    public function login(LoginAgentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $loginField = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = User::where($loginField, $validated['login'])->first();
        if (!$user) {
            return api_error('البريد الإلكتروني أو الهاتف غير مسجل لدينا.', [], 421);
        }

        if (!Hash::check($validated['password'], $user->password)) {
            return api_error('كلمة المرور غير صحيحة.', [], 421);
        }

        if (!Auth::attempt([$loginField => $validated['login'], 'password' => $validated['password']])) {
            return api_error('فشل عملية تسجيل الدخول.', [], 421);
        }

        /** @var User $user */
        $user = Auth::user();

        $tokenName = 'Hwnix_Cash_Agent_' . $validated['device_uuid'];
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
    public function refresh(RefreshAgentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user) {
            return api_error('غير مصرح بالعملية.', [], 401);
        }

        $validated = $request->validated();
        $request->user()->currentAccessToken()->delete();

        $tokenName = 'Hwnix_Cash_Agent_' . $validated['device_uuid'];
        $newToken = $user->createToken($tokenName, ['*'], now()->addDays(30))->plainTextToken;

        return api_success([
            'token' => $newToken,
        ], 'تم تجديد الرمز بنجاح.');
    }
}
