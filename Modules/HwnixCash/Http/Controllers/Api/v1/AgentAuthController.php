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

        $user = User::withoutGlobalScope('company_filter')->where($loginField, $validated['login'])->first();
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

    /**
     * جلب الشركات التي يمتلك المستخدم (Agent) صلاحية الوصول إليها.
     * يستخدم هذا المسار في شاشة اختيار الشركة داخل تطبيق الأندرويد.
     */
    public function getCompanies(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user) {
            return api_error('غير مصرح بالعملية.', [], 401);
        }

        // جلب الشركات المرتبطة بالمستخدم بشكل مباشر بناءً على الصلاحيات
        // سنستخدم نفس منطق الـ CompanyController لضمان توافق الصلاحيات.
        $query = Company::withoutGlobalScopes()->whereNull('deleted_at');

        if (
            $user->hasPermissionTo(perm_key('admin.super')) ||
            $user->hasPermissionTo(perm_key('companies.view_all'))
        ) {
            // وصول مطلق — يرى جميع الشركات
        } elseif ($user->hasPermissionTo(perm_key('companies.view_children'))) {
            // يرى ما أنشأه هو أو مرؤوسيه، بالإضافة إلى الشركات المرتبطة به عبر جدول company_user
            $descendantIds = method_exists($user, 'getDescendantUserIds')
                ? $user->getDescendantUserIds()
                : [];
            
            $query->where(function($q) use ($user, $descendantIds) {
                $q->whereIn('created_by', array_merge([$user->id], $descendantIds))
                  ->orWhereHas('users', function($q2) use ($user) {
                      $q2->where('users.id', $user->id);
                  });
            });
        } else {
            // وصول عادي — يرى الشركات المرتبطة به فقط عبر جدول company_user أو التي أنشأها
            $query->where(function($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('users', function($q2) use ($user) {
                      $q2->where('users.id', $user->id);
                  });
            });
        }

        $companies = $query->orderBy('name', 'asc')->get();

        $formattedCompanies = $companies->map(function ($company) {
            return [
                'id' => $company->id,
                'name' => $company->name,
                // يمكن إضافة logo إذا كانت مدعومة في الأندرويد
                // 'logo' => $company->logo_url 
            ];
        });

        return api_success(
            $formattedCompanies,
            $companies->isEmpty() ? 'لم يتم العثور على شركات.' : 'تم جلب الشركات بنجاح.'
        );
    }

    /**
     * Generate a Magic Link token for the Android App.
     */
    public function generateMagicLink(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return api_error('غير مصرح.', [], 401);
        }

        $token = \Illuminate\Support\Str::random(60);
        
        \Illuminate\Support\Facades\Cache::put("magic_login_{$token}", [
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'name' => $user->full_name ?? $user->name,
        ], now()->addMinutes(2));

        return api_success([
            'token' => $token
        ], 'تم إنشاء الرابط بنجاح.');
    }

    /**
     * Check the validity of a Magic Link token from the Web SPA.
     */
    public function checkMagicLink(\Illuminate\Http\Request $request): JsonResponse
    {
        $token = $request->input('token');
        $data = \Illuminate\Support\Facades\Cache::get("magic_login_{$token}");

        if (!$data) {
            return response()->json(['status' => 'invalid']);
        }

        return response()->json([
            'status' => 'valid',
            'target_user' => [
                'id' => $data['user_id'],
                'name' => $data['name'],
            ]
        ]);
    }

    /**
     * Execute the Magic Link token to login on the Web SPA.
     */
    public function executeMagicLink(\Illuminate\Http\Request $request): JsonResponse
    {
        $token = $request->input('token');
        $data = \Illuminate\Support\Facades\Cache::get("magic_login_{$token}");

        if (!$data) {
            return response()->json(['status' => 'invalid'], 400);
        }

        $user = \App\Models\User::find($data['user_id']);
        if (!$user) {
            return response()->json(['status' => 'invalid'], 400);
        }

        // إنشاء توكن للمتصفح
        $deviceName = $request->header('User-Agent') ?: 'MagicLink Web';
        $newToken = $user->createToken($deviceName, ['*'], now()->addHours(24))->plainTextToken;
        
        $user->load(['roles.permissions', 'permissions', 'branches', 'company.logo']);

        // حذف التوكن (Burn)
        \Illuminate\Support\Facades\Cache::forget("magic_login_{$token}");

        return api_success([
            'status' => 'success',
            'token' => $newToken,
            'user' => new \App\Http\Resources\User\UserWithPermissionsResource($user),
        ]);
    }
}
