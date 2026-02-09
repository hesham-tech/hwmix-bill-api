<?php

namespace App\Observers;

use App\Models\Installment;
use App\Models\InstallmentPlan;
use Illuminate\Support\Facades\Log;

class InstallmentObserver
{
    /**
     * Handle the Installment "updated" event.
     */
    public function updated(Installment $installment): void
    {
        if ($installment->isDirty(['remaining', 'status'])) {
            $this->syncInstallmentPlan($installment->installmentPlan);
        }
    }

    /**
     * Handle the Installment "deleted" event.
     */
    public function deleted(Installment $installment): void
    {
        $this->syncInstallmentPlan($installment->installmentPlan);
    }

    /**
     * مزامنة حالة وعوائد خطة التقسيط بناءً على الأقساط الفعلية.
     */
    protected function syncInstallmentPlan(?InstallmentPlan $plan): void
    {
        if (!$plan)
            return;

        try {
            // استخدام المجمعات لضمان أحدث القيم من قاعدة البيانات
            $totalInstallments = $plan->installments()->count();
            $canceledInstallments = $plan->installments()->where('status', 'canceled')->count();
            $paidInstallments = $plan->installments()->where('status', 'paid')->count();
            $partiallyPaidInstallments = $plan->installments()->where('status', 'partially_paid')->count();

            // تحديث المبلغ المتبقي الثابت في قاعدة البيانات
            $newRemaining = $plan->installments()->where('status', '!=', 'canceled')->sum('remaining');

            $newStatus = 'pending';
            if ($paidInstallments === ($totalInstallments - $canceledInstallments) && ($totalInstallments - $canceledInstallments) > 0) {
                $newStatus = 'paid';
            } elseif ($paidInstallments > 0 || $partiallyPaidInstallments > 0) {
                $newStatus = 'partially_paid';
            }

            // تحديث الحقول الثابتة في الـ Database لضمان التزامن
            $plan->update([
                'remaining_amount' => $newRemaining,
                'status' => $newStatus,
            ]);

            Log::info('InstallmentObserver: تم مزامنة خطة التقسيط تلقائياً.', [
                'plan_id' => $plan->id,
                'remaining' => $newRemaining,
                'status' => $newStatus
            ]);
        } catch (\Exception $e) {
            Log::error('InstallmentObserver: فشل مزامنة خطة التقسيط.', ['error' => $e->getMessage()]);
        }
    }
}
