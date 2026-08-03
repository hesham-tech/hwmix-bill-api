<?php
// متحكم مسار التهيئة والإعداد الأول (Onboarding) لتطبيقات الموبايل.

namespace Modules\HwnixCash\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\HwnixCash\Domain\Enums\WalletProvider;
use Modules\HwnixCash\Models\HwnixCashDevice;
use Modules\HwnixCash\Models\HwnixCashFinancialAccount;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Models\HwnixCashMessageSource;
use Modules\HwnixCash\Domain\Enums\LineStatus;

class AgentOnboardingController extends Controller
{
    /**
     * جلب قائمة المحافظ الخاصة بالشركة الحالية للمستخدم
     */
    public function getWallets(Request $request): JsonResponse
    {
        $wallets = HwnixCashFinancialAccount::with(['line', 'messageSource'])
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($wallet) {
                return [
                    'id' => $wallet->id,
                    'name' => $wallet->name,
                    'line_number' => $wallet->line ? $wallet->line->phone_number : null,
                    'sender' => $wallet->messageSource ? $wallet->messageSource->sender_identifier : null,
                    'balance' => $wallet->balance
                ];
            });

        return api_success($wallets, 'تم جلب المحافظ بنجاح');
    }

    /**
     * التحقق الفوري (Real-Time Validation) من الحقول أثناء كتابة المستخدم
     */
    public function validateField(Request $request): JsonResponse
    {
        $field = $request->input('field');
        $value = $request->input('value');
        
        if (!$field || !$value) {
            return api_error('يجب إرسال الحقل والقيمة');
        }

        $isValid = true;
        $message = 'متاح';

        switch ($field) {
            case 'wallet_name':
                $exists = HwnixCashFinancialAccount::where('name', $value)->exists();
                if ($exists) {
                    $isValid = false;
                    $message = 'هذه المحفظة موجودة بالفعل';
                }
                break;
            case 'sender_name':
                $exists = HwnixCashMessageSource::where('sender_identifier', $value)->exists();
                if ($exists) {
                    $isValid = false;
                    $message = 'هذا المرسل مرتبط بمحفظة أخرى';
                }
                break;
            case 'line_number':
                $exists = HwnixCashLine::where('phone_number', $value)->exists();
                if ($exists) {
                    $isValid = false;
                    $message = 'هذا الرقم مستخدم مسبقاً';
                }
                break;
            default:
                // قاعدة عامة لدعم توسع التحقق مستقبلا لأي جدول وعمود
                // field format: table.column (e.g., hwnix_cash_devices.device_name)
                if (str_contains($field, '.')) {
                    [$table, $column] = explode('.', $field);
                    $exists = DB::table($table)->where($column, $value)->exists();
                    if ($exists) {
                        $isValid = false;
                        $message = 'هذه القيمة مستخدمة مسبقاً';
                    }
                }
                break;
        }

        return api_success([
            'is_valid' => $isValid,
            'message' => $message
        ]);
    }

    /**
     * إتمام عملية الإعداد المجمعة (Transaction)
     */
    public function completeOnboarding(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_android_id' => 'required|string',
            'device_name' => 'required|string',
            'sim_phone' => 'required|string',
            'sim_carrier' => 'nullable|string',
            'wallet_name' => 'required|string',
            'sender' => 'required|string',
            'daily_withdraw_limit' => 'nullable|numeric',
            'daily_deposit_limit' => 'nullable|numeric',
        ]);

        $user = $request->user();

        try {
            DB::beginTransaction();

            // 1. إنشاء أو تحديث الجهاز (Find or Create)
            $device = HwnixCashDevice::firstOrCreate(
                ['android_id' => $validated['device_android_id']],
                [
                    'device_name' => $validated['device_name'],
                    'status' => 'active',
                    'created_by' => $user->id,
                ]
            );

            // 2. إنشاء الخط (Line)
            $line = HwnixCashLine::firstOrCreate(
                ['phone_number' => $validated['sim_phone']],
                [
                    'device_android_id' => $validated['device_android_id'],
                    'carrier' => $validated['sim_carrier'] ?? 'Unknown',
                    'status' => LineStatus::ACTIVE,
                    'created_by' => $user->id,
                    'slot_index' => 1,
                    'balance' => 0,
                    'actual_balance' => 0,
                    'daily_limit' => 0,
                ]
            );

            // 3. إنشاء مصدر الرسالة (Message Source)
            $messageSource = HwnixCashMessageSource::firstOrCreate(
                ['sender_identifier' => $validated['sender']],
                [
                    'provider' => WalletProvider::OTHER,
                    'is_active' => true,
                    'created_by' => $user->id,
                ]
            );

            // 4. إنشاء المحفظة (Financial Account)
            $wallet = HwnixCashFinancialAccount::firstOrCreate(
                ['name' => $validated['wallet_name']],
                [
                    'line_id' => $line->id,
                    'message_source_id' => $messageSource->id,
                    'balance' => 0,
                    'actual_balance' => 0,
                    'created_by' => $user->id,
                    'daily_withdraw_limit' => $validated['daily_withdraw_limit'] ?? null,
                    'daily_deposit_limit' => $validated['daily_deposit_limit'] ?? null,
                    'daily_withdraw_alert_type' => 'percentage',
                    'daily_withdraw_alert_value' => 80,
                    'daily_deposit_alert_type' => 'percentage',
                    'daily_deposit_alert_value' => 80,
                    'monthly_withdraw_alert_type' => 'percentage',
                    'monthly_withdraw_alert_value' => 80,
                    'monthly_deposit_alert_type' => 'percentage',
                    'monthly_deposit_alert_value' => 80,
                    'status' => 'active',
                ]
            );

            DB::commit();

            return api_success($wallet, 'تم إعداد المحفظة وبدء العمل بنجاح');
        } catch (\Exception $e) {
            DB::rollBack();
            return api_error('حدث خطأ أثناء حفظ الإعدادات: ' . $e->getMessage());
        }
    }
}
