<?php
// إجراء مزامنة وتحديث الخطوط والمحافظ المتاحة بالهاتف وتعديل أسمائها وسرعاتها وتفضيلاتها.

namespace Modules\HwnixCash\Actions;

use Modules\HwnixCash\DTOs\LineData;
use Modules\HwnixCash\Models\HwnixCashDevice;
use Modules\HwnixCash\Models\HwnixCashLine;

class SyncSimLinesAction
{
    /**
     * @param LineData[] $sims
     */
    public function execute(int $deviceId, string $deviceName, array $sims, int $companyId, int $userId): void
    {
        $device = HwnixCashDevice::find($deviceId);
        if (!$device) {
            return;
        }

        if (!empty($deviceName)) {
            $device->update(['device_name' => $deviceName, 'last_seen_at' => now()]);
        }

        // ── ضمان عدم التعارض ─────────────────────────────────────────────────────
        // قبل مزامنة الخطوط: التأكد أن كل خطوط هذا الجهاز تنتمي لنفس الشركة الحالية
        // إذا وُجد خط لشركة مختلفة على هذا الجهاز يُفصل فوراً (حالة البيع المكتشفة متأخراً)
        HwnixCashLine::where('device_android_id', $device->android_id)
            ->where('company_id', '!=', $companyId)
            ->update(['device_android_id' => null]);
        // ─────────────────────────────────────────────────────────────────────────

        foreach ($sims as $sim) {
            $phoneNumber = !empty($sim->phoneNumber) ? trim($sim->phoneNumber) : null;
            
            $lineModel = null;
            
            // 1. مطابقة برقم الهاتف الفعلي (فقط ضمن نطاق الشركة الحالية)
            if ($phoneNumber) {
                // نفضل الخطوط المرتبطة بنفس الجهاز أو المجمّدة لتجنب أخذ خط نشط لجهاز آخر إن أمكن
                $lineModel = HwnixCashLine::where('phone_number', $phoneNumber)
                    ->where('company_id', $companyId)
                    ->orderByRaw('device_android_id = ? DESC', [$device->android_id])
                    ->first();
            }
            
            // 2. المطابقة بـ slot_index أو subscription_id على نفس الجهاز ونفس الشركة
            if (!$lineModel) {
                $lineModel = HwnixCashLine::where('device_android_id', $device->android_id)
                    ->where('company_id', $companyId)
                    ->where(function ($query) use ($sim) {
                        $query->where('subscription_id', $sim->subscriptionId)
                              ->orWhere('slot_index', $sim->slotIndex);
                    })
                    ->first();
            }

            $updateData = [
                'device_android_id' => $device->android_id,
                'company_id' => $companyId,
                'created_by' => $userId,
                'slot_index' => $sim->slotIndex,
                'subscription_id' => $sim->subscriptionId,
                'carrier' => $sim->carrier,
                'phone_number' => $phoneNumber,
                'daily_withdraw_limit' => $sim->dailyWithdrawLimit ?? 60000.00,
                'daily_deposit_limit' => $sim->dailyDepositLimit ?? 60000.00,
                'monthly_withdraw_limit' => $sim->monthlyWithdrawLimit ?? 200000.00,
                'monthly_deposit_limit' => $sim->monthlyDepositLimit ?? 200000.00,
                'status' => 'active',
            ];

            if ($lineModel) {
                $lineModel->update($updateData);
            } else {
                HwnixCashLine::create($updateData);
            }
        }
    }
}
