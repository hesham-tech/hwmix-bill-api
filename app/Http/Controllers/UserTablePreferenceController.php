<?php

namespace App\Http\Controllers;

use App\Models\UserTablePreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * متحكم لإدارة تفضيلات المستخدم الخاصة بخصائص الجداول والواجهة.
 */
class UserTablePreferenceController extends Controller
{
    /**
     * السجل المعتمد للجداول والأعمدة المسموح بها في النظام لأسباب أمنية.
     */
    protected static array $allowedTables = [
        'products.index' => [
            'data-table-expand', 'name', 'sku', 'price_range', 'total_available_quantity', 
            'category_brand', 'active', 'created_at', 'updated_at', 'actions', 
            'description', 'primary_image_url', 'slug', 'product_type', 'min_price', 
            'max_price', 'require_stock', 'featured', 'sales_count', 'returnable', 
            'is_downloadable', 'download_limit', 'available_keys_count', 'validity_days', 
            'expires_at', 'published_at', 'desc', 'desc_long', 'visibility', 'created_by_name'
        ],
        'invoices.index' => [
            'invoice_number', 'customer', 'issue_date', 'financials', 'status', 'actions', 
            'client_name', 'net_amount', 'tax_amount', 'total_amount', 'created_at', 'updated_at',
            'gross_amount', 'total_discount', 'paid_amount', 'remaining_amount', 'due_date',
            'notes', 'reference_number'
        ],
        'users.index' => [
            'full_name', 'phone', 'roles', 'status', 'actions', 
            'name', 'email', 'active', 'created_at', 'updated_at',
            'nickname', 'username', 'position', 'active_branch_balance', 'total_branches_balance'
        ],
        'customers.index' => [
            'full_name', 'phone', 'balance_display', 'status', 'actions',
            'name', 'nickname', 'username', 'email', 'position',
            'active_branch_balance', 'created_at', 'updated_at'
        ],
        'payments.index' => [
            'invoice', 'amount', 'payment_method', 'payment_date', 'actions',
            'method', 'notes', 'is_split', 'created_at', 'updated_at'
        ],
        'expenses.index' => [
            'expense_date', 'expense_category', 'notes', 'amount', 'reference_number', 'creator', 'actions',
            'payment_method', 'created_at'
        ],
        'subscriptions.index' => [
            'customer', 'service', 'financial', 'billing', 'status', 'actions',
            'unique_identifier', 'start_date', 'ends_at', 'billing_cycle', 'price',
            'partial_payment', 'auto_renew', 'renewal_type', 'notes', 'created_at', 'updated_at'
        ],
        'services.index' => [
            'name', 'default_price', 'created_at', 'actions',
            'description', 'period_unit', 'period_value', 'updated_at'
        ],
        'warehouses.index' => [
            'name', 'location', 'status', 'actions',
            'manager', 'capacity', 'description', 'created_at', 'updated_at'
        ],
    ];

    /**
     * جلب تفضيلات محددة أو كافة التفضيلات للمستخدم والشركة النشطة.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $companyId = $user->active_company_id;

            if (!$companyId) {
                return api_error('الشركة النشطة غير محددة.', [], 400);
            }

            $keys = $request->query('keys');
            $query = UserTablePreference::where('user_id', $user->id)
                ->where('company_id', $companyId);

            if ($keys) {
                $keysArray = explode(',', $keys);
                $query->whereIn('table_key', $keysArray);
            }

            $preferences = $query->get()->pluck('preferences', 'table_key');

            return api_success($preferences, 'تم جلب تفضيلات الواجهة بنجاح.');
        } catch (\Throwable $e) {
            Log::error('Error fetching UI preferences: ' . $e->getMessage());
            return api_exception($e, 500);
        }
    }

    /**
     * حفظ أو تحديث تفضيلات جدول محدد.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $companyId = $user->active_company_id;

            if (!$companyId) {
                return api_error('الشركة النشطة غير محددة.', [], 400);
            }

            $validated = $request->validate([
                'table_key' => 'required|string',
                'preferences' => 'required|array',
            ]);

            $tableKey = $validated['table_key'];
            $prefs = $validated['preferences'];

            // 1. التحقق الأمني: هل الجدول معرف مسبقاً؟
            if (!isset(self::$allowedTables[$tableKey])) {
                return api_error("مفتاح الجدول الممرر غير مصرح به: {$tableKey}", [], 422);
            }

            // 2. التحقق الأمني: هل الأعمدة الممرضة صالحة؟
            if (isset($prefs['columns']) && is_array($prefs['columns'])) {
                $allowedColumns = self::$allowedTables[$tableKey];
                foreach ($prefs['columns'] as $col) {
                    if (!isset($col['key']) || !in_array($col['key'], $allowedColumns)) {
                        return api_error("مفتاح العمود غير مصرح به: " . ($col['key'] ?? 'N/A'), [], 422);
                    }
                }
            }

            // إضافة بيانات التحديث
            $prefs['updated_at'] = now()->toIso8601String();
            if (!isset($prefs['version'])) {
                $prefs['version'] = 1;
            }

            $preference = UserTablePreference::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'company_id' => $companyId,
                    'table_key' => $tableKey,
                ],
                [
                    'preferences' => $prefs,
                    'created_by' => $user->id,
                ]
            );

            return api_success($preference->preferences, 'تم حفظ تفضيلات الجدول بنجاح.');

        } catch (ValidationException $e) {
            return api_error('بيانات تفضيلات غير صالحة.', $e->errors(), 422);
        } catch (\Throwable $e) {
            Log::error('Error saving UI preferences: ' . $e->getMessage());
            return api_exception($e, 500);
        }
    }

    /**
     * إعادة ضبط تفضيلات جدول محدد لاستعادة القيم الافتراضية.
     */
    public function reset(Request $request, string $tableKey): JsonResponse
    {
        try {
            $user = $request->user();
            $companyId = $user->active_company_id;

            if (!$companyId) {
                return api_error('الشركة النشطة غير محددة.', [], 400);
            }

            UserTablePreference::where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->where('table_key', $tableKey)
                ->delete();

            return api_success(null, 'تم إعادة ضبط تفضيلات الجدول للحالة الافتراضية.');
        } catch (\Throwable $e) {
            Log::error('Error resetting UI preferences: ' . $e->getMessage());
            return api_exception($e, 500);
        }
    }

    /**
     * إعادة ضبط كافة تفضيلات الجداول للمستخدم الحالي.
     */
    public function resetAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $companyId = $user->active_company_id;

            if (!$companyId) {
                return api_error('الشركة النشطة غير محددة.', [], 400);
            }

            UserTablePreference::where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->delete();

            return api_success(null, 'تم إعادة ضبط جميع تفضيلات الواجهة بنجاح.');
        } catch (\Throwable $e) {
            Log::error('Error resetting all UI preferences: ' . $e->getMessage());
            return api_exception($e, 500);
        }
    }
}
