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

        foreach ($sims as $sim) {
            HwnixCashLine::updateOrCreate(
                [
                    'device_android_id' => $device->android_id,
                    'slot_index' => $sim->slotIndex,
                ],
                [
                    'company_id' => $companyId,
                    'created_by' => $userId,
                    'subscription_id' => $sim->subscriptionId,
                    'carrier' => $sim->carrier,
                    'phone_number' => $sim->phoneNumber,
                    'daily_withdraw_limit' => $sim->dailyWithdrawLimit,
                    'daily_deposit_limit' => $sim->dailyDepositLimit,
                    'monthly_withdraw_limit' => $sim->monthlyWithdrawLimit,
                    'monthly_deposit_limit' => $sim->monthlyDepositLimit,
                    'status' => 'active',
                ]
            );
        }
    }
}
