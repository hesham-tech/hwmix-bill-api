<?php
// متحكم لإدارة خطوط الاتصال والمحافظ الخاصة بالـ Agent في الأندرويد.

namespace Modules\HwnixCash\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Models\HwnixCashDevice;
use Modules\HwnixCash\Transformers\LineResource;

class AgentLineController extends Controller
{
    /**
     * تنفيذ تسوية مالية من تطبيق الأندرويد.
     */
    public function reconcile(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $request->validate([
            'device_id' => 'required|numeric',
            'slot_index' => 'required|numeric',
            'target_balance' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:500',
        ]);

        $deviceId = $request->device_id;

        $device = HwnixCashDevice::where('id', $deviceId)->where('company_id', $companyId)->first();
        if (!$device) {
            return api_error('الجهاز غير مسجل أو لا ينتمي لشركتك.', [], 403);
        }

        $line = HwnixCashLine::where('slot_index', $request->slot_index)
            ->where('company_id', $companyId)
            ->where('device_android_id', $device->android_id)
            ->first();

        if (!$line) {
            return api_error('الخط المالي غير متوفر أو غير مرتبط بهذا الجهاز.', [], 404);
        }

        $account = $line->financialAccounts()->first();

        if (!$account) {
            return api_error('لا توجد محفظة مالية مرتبطة بهذا الخط.', [], 404);
        }

        $targetBalance = (float) $request->target_balance;
        $oldBalance = (float) $account->balance;
        $difference = round($targetBalance - $oldBalance, 2);

        $providerStr = mb_strtolower($line->carrier ?? '');
        $providerEnum = \Modules\HwnixCash\Domain\Enums\WalletProvider::OTHER->value;
        
        if (str_contains($providerStr, 'vodafone') || str_contains($providerStr, 'فودافون')) {
            $providerEnum = \Modules\HwnixCash\Domain\Enums\WalletProvider::VODAFONE_CASH->value;
        } elseif (str_contains($providerStr, 'orange') || str_contains($providerStr, 'اورنج') || str_contains($providerStr, 'أورنج')) {
            $providerEnum = \Modules\HwnixCash\Domain\Enums\WalletProvider::ORANGE_CASH->value;
        } elseif (str_contains($providerStr, 'etisalat') || str_contains($providerStr, 'اتصالات')) {
            $providerEnum = \Modules\HwnixCash\Domain\Enums\WalletProvider::ETISALAT_CASH->value;
        } elseif (str_contains($providerStr, 'we') || str_contains($providerStr, 'وي')) {
            $providerEnum = \Modules\HwnixCash\Domain\Enums\WalletProvider::WE_PAY->value;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($account, $line, $user, $companyId, $targetBalance, $oldBalance, $difference, $request, $providerEnum) {
            // 1. تحديث الرصيد الحسابي للمحفظة
            $account->update([
                'balance' => $targetBalance,
            ]);

            // 2. تسجيل قيد/معاملة تسوية مالية رسمية بجدول المعاملات للتدقيق المحاسبي (FAC-001)
            \Modules\HwnixCash\Models\HwnixCashWalletTransaction::create([
                'company_id' => $companyId,
                'created_by' => $user->id,
                'financial_account_id' => $account->id,
                'operation_type' => \Modules\HwnixCash\Domain\Enums\WalletOperationType::RECONCILIATION->value,
                'provider' => $providerEnum,
                'status' => \Modules\HwnixCash\Domain\Enums\WalletTransactionStatus::SUCCESS->value,
                'source' => \Modules\HwnixCash\Domain\Enums\WalletTransactionSource::MANUAL->value,
                'amount' => abs($difference),
                'fee' => 0.00,
                'balance_after' => $targetBalance,
                'currency' => 'EGP',
                'operation_number' => 'REC-APP-' . date('YmdHis') . '-' . $account->id,
                'operation_at' => now(),
                'raw_sms' => 'تسوية مالية يدوية من تطبيق الأندرويد',
                'metadata' => [
                    'type' => 'balance_reconciliation',
                    'old_balance' => $oldBalance,
                    'new_balance' => $targetBalance,
                    'difference' => $difference,
                    'actual_balance' => (float) $account->actual_balance,
                    'note' => $request->note ?? 'تسوية تمت من خلال تطبيق الهاتف',
                    'agent_device_id' => $request->device_id,
                ],
            ]);
        });

        return api_success(new LineResource($line->fresh('device')), 'تمت تسوية الرصيد الحسابي بنجاح.');
    }

    /**
     * حذف الخط وكل ما يتعلق به نهائياً (Force Delete)
     */
    public function delete(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $request->validate([
            'device_id' => 'required|numeric',
            'slot_index' => 'required|numeric',
        ]);

        $device = HwnixCashDevice::where('id', $request->device_id)
            ->where('company_id', $companyId)
            ->first();

        if (!$device) {
            return api_error('الجهاز غير مسجل أو لا ينتمي لشركتك.', [], 403);
        }

        $line = HwnixCashLine::where('slot_index', $request->slot_index)
            ->where('company_id', $companyId)
            ->where('device_android_id', $device->android_id)
            ->first();

        if (!$line) {
            return api_error('الخط غير موجود.', [], 404);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($line, $device, $companyId) {
            // حذف الحركات المالية (Transactions) المرتبطة بمحافظ هذا الخط
            $financialAccountIds = $line->financialAccounts()->pluck('id');
            if ($financialAccountIds->isNotEmpty()) {
                \Modules\HwnixCash\Models\HwnixCashWalletTransaction::whereIn('financial_account_id', $financialAccountIds)
                    ->forceDelete();
                
                // حذف المحافظ المالية
                $line->financialAccounts()->forceDelete();
            }

            // حذف الرسائل ونتائج تحليلها
            $messageIds = $line->messages()->pluck('id');
            if ($messageIds->isNotEmpty()) {
                \Modules\HwnixCash\Models\HwnixCashSmsAnalysisResult::whereIn('message_id', $messageIds)
                    ->forceDelete();
                
                $line->messages()->forceDelete();
            }

            // حذف الخط
            $line->forceDelete();

            // التحقق من وجود خطوط أخرى للجهاز
            $otherLinesCount = HwnixCashLine::where('device_android_id', $device->android_id)
                ->where('company_id', $companyId)
                ->count();

            // إذا كان الجهاز لم يعد مرتبطاً بأي خطوط، نقوم بحذفه أيضاً
            if ($otherLinesCount === 0) {
                $device->settings()->forceDelete();
                $device->commands()->forceDelete();
                $device->heartbeats()->forceDelete();
                $device->messages()->forceDelete(); // لأي رسائل أخرى مرتبطة بالجهاز وليست مرتبطة بخط
                $device->forceDelete();
            }
        });

        return api_success(null, 'تم حذف الخط وكافة بياناته نهائياً بنجاح.');
    }
}
