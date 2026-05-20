<?php

namespace App\Http\Controllers;

use App\Http\Resources\User\UserWithPermissionsResource;
use App\Models\UserTablePreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * متحكم جلب البيانات الأساسية عند إقلاع التطبيق (Bootstrap).
 */
class BootstrapController extends Controller
{
    /**
     * جلب بيانات المصادقة، الشركة النشطة، الصلاحيات، وتفضيلات الواجهة الأساسية.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return api_unauthorized('المستخدم غير مصادق عليه.');
            }

            // تحميل العلاقات اللازمة
            $user->load(['company.logo', 'companies.logo', 'roles.permissions', 'permissions', 'branches']);

            // تفضيلات الشاشات الأساسية (الرئيسية) فقط لتجنب تضخم Payload الـ Bootstrap
            $basicScreenKeys = ['products.index', 'invoices.index', 'users.index'];
            $screenPreferences = [];

            if ($user->active_company_id) {
                $screenPreferences = UserTablePreference::where('user_id', $user->id)
                    ->where('company_id', $user->active_company_id)
                    ->whereIn('table_key', $basicScreenKeys)
                    ->get()
                    ->pluck('preferences', 'table_key');
            }

            return api_success([
                'user' => new UserWithPermissionsResource($user),
                'screen_preferences' => $screenPreferences,
                'feature_flags' => [
                    'digital_products' => (bool)($user->company?->settings['digital_products'] ?? false),
                ],
            ], 'تم تحميل بيانات الإقلاع بنجاح.');

        } catch (\Throwable $e) {
            Log::error('Bootstrap Endpoint Error: ' . $e->getMessage());
            return api_exception($e, 500);
        }
    }
}
