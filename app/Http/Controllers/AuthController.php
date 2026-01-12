<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException; // استيراد ValidationException
use Throwable; // استيراد Throwable

/**
 * Class AuthController
 *
 * تحكم في عمليات التسجيل وتسجيل الدخول للمستخدمين
 *
 * @package App\Http\Controllers
 */
class AuthController extends Controller
{
    /**
     * @group 01. إدارة المصادقة
     * 
     * تسجيل مستخدم جديد
     * 
     * إنشاء حساب مستخدم جديد وربطه بالشركة الافتراضية.
     * 
     * @bodyParam phone string required رقم الهاتف (يجب أن يكون فريداً). Example: 01099223344
     * @bodyParam password string required كلمة المرور (8 أحرف على الأقل). Example: password123
     * @bodyParam full_name string الاسم الكامل للمستخدم. Example: هشام محمد
     * @bodyParam nickname string الاسم الحركي أو اللقب. Example: أبو هيبة
     * @bodyParam email string البريد الإلكتروني (اختياري وفريد). Example: user@example.com
     * 
     * @response 201 {
     *  "success": true,
     *  "data": { "user": { "id": 1, "full_name": "..." }, "token": "..." },
     *  "message": "تم تسجيل المستخدم بنجاح."
     * }
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'phone' => 'required|unique:users,phone',
                'password' => 'required|string|min:8',
                'company_id' => 'nullable|string|min:8',
                'email' => 'nullable|email|unique:users,email',
                'full_name' => 'nullable|string|max:255',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'nickname' => 'nullable|string|max:255',
            ]);

            \Log::info('Attempting to create user', ['phone' => $validated['phone'], 'email' => $validated['email'] ?? 'N/A']);

            $fullName = $validated['full_name'] ?? trim(($validated['first_name'] ?? '') . ' ' . ($validated['last_name'] ?? ''));

            $user = User::create([
                'phone' => $validated['phone'],
                'company_id' => $companyId,
                'full_name' => $fullName ?: null,
                'nickname' => $validated['nickname'] ?? null,
                'password' => Hash::make($validated['password']),
            ]);
            \Log::info('User created successfully', ['user_id' => $user->id]);

            // **[التعديل الجديد]: ربط المستخدم بالشركة الافتراضية**
            // هذا السطر هو الذي ينشئ سجل في جدول company_user ويطلق المراقب.
            $user->companies()->attach($companyId, [
                // يجب توفير created_by في جدول company_user
                // نفترض أن المستخدم الذي تم إنشاؤه حديثًا هو 'created_by' لنفسه في الوقت الحالي.
                'created_by' => $user->id,
                'user_phone' => $user->phone,
                'full_name_in_company' => $user->full_name,
                'nickname_in_company' => $user->nickname,
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            // **[الحذف]: تم حذف السطر الذي كان يستخدم الدالة القديمة يدوياً:**
            // app(\App\Services\CashBoxService::class)->ensure=CashBoxForUser($user);

            // إنشاء صناديق المستخدم الافتراضية لكل شركة (تتم الآن تلقائياً بواسطة المراقب)
            return api_success([
                'user' => new UserResource($user),
                'token' => $token,
            ], 'تم تسجيل المستخدم بنجاح.', 201);
        } catch (ValidationException $e) {
            \Log::warning('Registration Validation Failed', [
                'errors' => $e->errors(),
                'input' => $request->except(['password', 'password_confirmation'])
            ]);
            return api_error('فشل التحقق من صحة البيانات أثناء التسجيل.', $e->errors(), 422);
        } catch (Throwable $e) {
            \Log::error('Registration Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return api_exception($e, 500);
        }
    }

    /**
     * @group 01. إدارة المصادقة
     * 
     * تسجيل دخول
     * 
     * الحصول على رمز الوصول (Access Token) باستخدام الهاتف أو البريد الإلكتروني.
     * 
     * @bodyParam login string required الهاتف أو البريد الإلكتروني. Example: 01099223344
     * @bodyParam password string required كلمة المرور. Example: password123
     * 
     * @response 200 {
     *  "success": true,
     *  "data": { "token": "...", "user": {...} },
     *  "message": "تم تسجيل دخول المستخدم بنجاح."
     * }
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'login' => 'required',
                'password' => 'required',
            ]);

            $loginField = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
            \Log::info('Login attempt started', ['field' => $loginField, 'value' => $validated['login']]);

            $user = User::where($loginField, $validated['login'])->first();
            if (!$user) {
                \Log::warning('Login failed: User not found in database', ['login' => $validated['login']]);
                return api_error('بيانات الاعتماد غير صالحة.', [], 422);
            }

            \Log::info('User found, checking password', ['user_id' => $user->id]);
            if (!Hash::check($validated['password'], $user->password)) {
                \Log::warning('Login failed: Password hashing mismatch', ['user_id' => $user->id]);
                return api_error('بيانات الاعتماد غير صالحة.', [], 422);
            }

            if (!Auth::attempt([$loginField => $validated['login'], 'password' => $validated['password']])) {
                \Log::error('Login failed: Auth::attempt returned false but Hash::check passed', ['user_id' => $user->id]);
                return api_error('بيانات الاعتماد غير صالحة.', [], 422);
            }
            \Log::info('Login successful', ['user_id' => $user->id]);

            /** @var \App\Models\User $user */
            $user = Auth::user();
            $token = $user->createToken('auth_token')->plainTextToken;

            return api_success([
                'user' => new UserResource($user),
                'token' => $token,
            ], 'تم تسجيل دخول المستخدم بنجاح.');
        } catch (ValidationException $e) {
            return api_error('فشل التحقق من صحة البيانات أثناء تسجيل الدخول.', $e->errors(), 422);
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 01. إدارة المصادقة
     * 
     * تسجيل الخروج
     * 
     * إبطال رمز الوصول الحالي للمستخدم.
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            /** @var \Laravel\Sanctum\PersonalAccessToken|null $token */
            $user = $request->user();
            if ($user) {
                /** @var \Laravel\Sanctum\PersonalAccessToken $token */
                $token = $user->currentAccessToken();
                $token->delete();
            } else {
                // إذا لم يكن هناك مستخدم مصادق عليه، فلا يوجد رمز مميز لحذفه
                return api_error('لا يوجد مستخدم مصادق عليه لتسجيل الخروج.', [], 401);
            }

            return api_success([], 'تم تسجيل الخروج بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 01. إدارة المصادقة
     * 
     * بياناتي (الملف الشخصي)
     * 
     * استعادة بيانات المستخدم المصادق عليه حالياً.
     */
    public function me(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = $request->user();

            if (!$user) {
                return api_unauthorized('المستخدم غير مصادق عليه.');
            }

            return api_success(new UserResource($user), 'تم استرداد بيانات المستخدم بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 01. إدارة المصادقة
     * 
     * فحص حالة تسجيل الدخول
     */
    public function checkLogin(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            if (Auth::check()) {
                return api_success(new UserResource($user), 'المستخدم مسجل الدخول.');
            }

            return api_unauthorized('المستخدم غير مصادق عليه.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}
