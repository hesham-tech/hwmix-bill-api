<?php

namespace App\Services;

use Exception;
use App\Models\User;
use App\Models\Installment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\InstallmentPlan;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPaymentDetail;
use Illuminate\Support\Facades\Log;
use App\Models\PaymentMethod;

class InstallmentPaymentService
{
    /**
     * يدفع الأقساط المحددة، مع التعامل مع المدفوعات الجزئية والمبالغ الزائدة.
     *
     * @param array $installmentIds معرفات الأقساط المراد دفعها.
     * @param float $amount المبلغ الإجمالي المدفوع.
     * @param array $options خيارات إضافية (user_id, installment_plan_id, payment_method_id, cash_box_id, notes, paid_at).
     * @return array يحتوي على الدفعة الرئيسية والأقساط المتأثرة.
     * @throws \Throwable
     */
    public function payInstallments(array $installmentIds, float $amount, array $options = [])
    {
        DB::beginTransaction();

        try {
            $remainingAmountToDistribute = $amount;
            $totalAmountSuccessfullyPaid = 0;

            $authUser = Auth::user();

            $installmentPlan = InstallmentPlan::with('installments')->find($options['installment_plan_id'] ?? null);
            if (!$installmentPlan) {
                throw new Exception('InstallmentPaymentService: خطة التقسيط غير موجودة.');
            }

            $cashBoxId = $options['cash_box_id'] ?? null;
            if (!$cashBoxId) {
                throw new Exception('InstallmentPaymentService: لم يتم تحديد صندوق نقدي للموظف المستلم.');
            }

            $clientUser = User::find($installmentPlan->user_id);
            if (!$clientUser) {
                throw new Exception('InstallmentPaymentService: لم يتم العثور على العميل المرتبط بخطة التقسيط.');
            }
            $clientCashBoxId = $options['user_cash_box_id'] ?? null;
            if (!$clientCashBoxId) {
                Log::warning('InstallmentPaymentService: لم يتم العثور على صندوق نقدي افتراضي للعميل. استخدام صندوق الموظف المستلم كبديل.', ['user_id' => $clientUser->id, 'fallback_cash_box_id' => $clientCashBoxId]);
            }

            $paymentMethodName = 'نقداً';
            if (isset($options['payment_method_id'])) {
                $paymentMethod = PaymentMethod::find($options['payment_method_id']);
                if ($paymentMethod) {
                    $paymentMethodName = $paymentMethod->name;
                }
            }

            $installmentPayment = InstallmentPayment::create([
                'installment_plan_id' => $installmentPlan->id,
                'company_id' => $installmentPlan->company_id,
                'created_by' => $authUser->id,
                'payment_date' => $options['paid_at'] ?? now(),
                'amount_paid' => 0,
                'payment_method' => $paymentMethodName,
                'notes' => $options['notes'] ?? '',
                'cash_box_id' => $cashBoxId,
                'reference_number' => $options['reference_number'] ?? null,
            ]);

            // **التعديل هنا: جلب الأقساط بطريقة تسمح بالتدفق التلقائي**
            // ابدأ بالأقساط المحددة ثم انتقل للمستحقة الأخرى
            $query = $installmentPlan->installments()
                ->where('status', '!=', 'canceled')
                ->where('remaining', '>', 0);

            if (!empty($installmentIds)) {
                // قم بترتيب الأقساط المحددة أولاً، ثم باقي الأقساط حسب تاريخ الاستحقاق
                $selectedInstallments = (clone $query)->whereIn('id', $installmentIds)->orderBy('due_date')->get();
                $otherDueInstallments = (clone $query)->whereNotIn('id', $installmentIds)->orderBy('due_date')->get();
                $installmentsToProcess = $selectedInstallments->merge($otherDueInstallments);
            } else {
                $installmentsToProcess = $query->orderBy('due_date')->get();
            }

            $affectedInstallments = collect();

            foreach ($installmentsToProcess as $installment) {
                if (bccomp($remainingAmountToDistribute, '0.00', 2) <= 0) {
                    break;
                }

                $amountToApplyToCurrentInstallment = min($remainingAmountToDistribute, $installment->remaining);
                $newRemaining = bcsub($installment->remaining, $amountToApplyToCurrentInstallment, 2);
                $newStatus = $installment->status;

                if (bccomp($newRemaining, '0.00', 2) <= 0) {
                    $newStatus = 'paid';
                } elseif (bccomp((string) $amountToApplyToCurrentInstallment, '0.00', 2) > 0 && bccomp($newRemaining, '0.00', 2) > 0) {
                    $newStatus = 'partially_paid';
                }

                $installment->update([
                    'remaining' => $newRemaining,
                    'status' => $newStatus,
                    'paid_at' => ($newStatus === 'paid' && !$installment->paid_at) ? ($options['paid_at'] ?? now()) : $installment->paid_at,
                ]);

                InstallmentPaymentDetail::create([
                    'installment_payment_id' => $installmentPayment->id,
                    'installment_id' => $installment->id,
                    'amount_paid' => $amountToApplyToCurrentInstallment,
                ]);

                $remainingAmountToDistribute = bcsub($remainingAmountToDistribute, $amountToApplyToCurrentInstallment, 2);
                $totalAmountSuccessfullyPaid = bcadd($totalAmountSuccessfullyPaid, $amountToApplyToCurrentInstallment, 2);
                $affectedInstallments->push($installment->fresh());
            }

            $installmentPayment->update(['amount_paid' => $totalAmountSuccessfullyPaid]);
            $this->updateInstallmentPlanStatus($installmentPlan);

            $parentInvoice = $installmentPlan->invoice;

            // إنشاء سجل دفع عام (Payment) ليظهر في الداشبورد والتقارير الموحدة
            \App\Models\Payment::create([
                'user_id' => $clientUser->id,
                'company_id' => $installmentPlan->company_id,
                'created_by' => $authUser->id,
                'payment_date' => $options['paid_at'] ?? now(),
                'amount' => $totalAmountSuccessfullyPaid,
                'method' => $paymentMethodName,
                'notes' => "سداد أقساط - فاتورة #{$parentInvoice?->invoice_number} - دفعة #{$installmentPayment->id}",
                'payment_method_id' => $options['payment_method_id'] ?? null,
                'cash_box_id' => $cashBoxId,
            ]);

            // إيداع المبلغ في خزنة الموظف
            $depositResultStaff = $authUser->deposit($totalAmountSuccessfullyPaid, $cashBoxId, "تحصيل أقساط - دفعة #{$installmentPayment->id}");
            if ($depositResultStaff !== true) {
                throw new Exception('InstallmentPaymentService: فشل إيداع المبلغ في خزنة الموظف: ' . json_encode($depositResultStaff));
            }

            // تحديث مديونية العميل (إيداع وهمي لتقليل الدين)
            $depositResultClient = $clientUser->deposit($totalAmountSuccessfullyPaid, $clientCashBoxId, "سداد أقساط - دفعة #{$installmentPayment->id}");
            if ($depositResultClient !== true) {
                throw new Exception('InstallmentPaymentService: فشل تحديث رصيد العميل (تقليل الدين): ' . json_encode($depositResultClient));
            }

            // تحديث الفاتورة الأم (داخل الـ Transaction لضمان الكل أو لا شيء)
            if ($parentInvoice) {
                $parentInvoice->paid_amount += $totalAmountSuccessfullyPaid;
                $parentInvoice->remaining_amount = max(0, $parentInvoice->net_amount - $parentInvoice->paid_amount);
                $parentInvoice->updatePaymentStatus();
            }

            DB::commit();

            // جلب القسط القادم لإظهاره للمستخدم (خارج الـ Transaction لأنه للقراءة فقط)
            $nextInstallment = $installmentPlan->installments()
                ->where('status', 'pending')
                ->where('remaining', '>', 0)
                ->orderBy('due_date')
                ->first();

            return [
                'installmentPayment' => $installmentPayment,
                'installments' => $affectedInstallments,
                'excess_amount' => (float) $remainingAmountToDistribute,
                'next_installment' => $nextInstallment,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('InstallmentPaymentService: فشل في دفع الأقساط.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'installment_ids' => $installmentIds,
                'amount_attempted' => $amount
            ]);
            throw $e;
        }
    }
    /**
     * تحديث حالة خطة الأقساط بناءً على حالة الأقساط الفردية.
     *
     * @param InstallmentPlan $installmentPlan
     * @return void
     */
    protected function updateInstallmentPlanStatus(InstallmentPlan $installmentPlan): void
    {
        $totalInstallments = $installmentPlan->installments->count();
        $paidInstallments = $installmentPlan->installments->where('status', 'paid')->count();
        $partiallyPaidInstallments = $installmentPlan->installments->where('status', 'partially_paid')->count();
        $canceledInstallments = $installmentPlan->installments->where('status', 'canceled')->count();

        // تحديث إجمالي المبلغ المتبقي لخطة الأقساط
        $newRemainingAmountPlan = $installmentPlan->installments->sum('remaining');
        $installmentPlan->update(['remaining_amount' => $newRemainingAmountPlan]);

        if ($paidInstallments === ($totalInstallments - $canceledInstallments)) {
            // إذا تم دفع جميع الأقساط (باستثناء الملغاة)
            $installmentPlan->update(['status' => 'paid']);
        } elseif ($paidInstallments > 0 || $partiallyPaidInstallments > 0) {
            // إذا تم دفع أي قسط كليًا أو جزئيًا
            $installmentPlan->update(['status' => 'partially_paid']);
        } else {
            // إذا لم يتم دفع أي شيء
            $installmentPlan->update(['status' => 'pending']);
        }
    }
}
