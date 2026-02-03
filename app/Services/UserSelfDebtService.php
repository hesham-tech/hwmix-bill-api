<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Invoice; // يجب استيراد نموذج الفاتورة
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class UserSelfDebtService
{
    /**
     * معالجة دين البيع للموظف لنفسه عند إنشاء فاتورة بيع بالتقسيط.
     *
     * @param User $user المستخدم الموظف.
     * @param Invoice $invoice الفاتورة المرتبطة.
     * @param float $downPayment الدفعة الأولى.
     * @param float $totalInstallmentAmount إجمالي مبلغ الأقساط.
     * @param int|null $companyCashBoxId معرف صندوق النقدية للشركة.
     * @param int|null $userCashBoxId معرف صندوق النقدية للمستخدم (الموظف كعميل).
     * @return void
     * @throws \Throwable
     */
    public function handleSelfSaleDebt(User $user, Invoice $invoice, float $downPayment, float $totalInstallmentAmount, ?int $companyCashBoxId = null, ?int $userCashBoxId = null): void
    {
        try {
            $companyId = $invoice->company_id ?? $user->company_id;
            $installmentDebt = $totalInstallmentAmount - $downPayment;
            $totalDebt = $totalInstallmentAmount;

            // تسجيل إجمالي مديونية الفاتورة (مقدم + تقسيط) في معاملة واحدة واضحة
            if ($totalDebt > 0) {
                $this->createTransaction(
                    user: $user,
                    type: 'مديونية فاتورة بيع (شراء لنفسه)',
                    amount: $totalDebt,
                    description: "تسجيل إجمالي مديونية فاتورة التقسيط رقم #{$invoice->invoice_number} (مقدم: $downPayment + تقسيط: $installmentDebt)",
                    cashBoxId: $userCashBoxId,
                    companyId: $companyId,
                    invoiceId: $invoice->id,
                    transactionType: 'withdrawal'
                );
            }
        } catch (\Throwable $e) {
            Log::error('UserSelfDebtService: فشل في معالجة دين البيع للموظف لنفسه.', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id, 'user_id' => $user->id]);
            throw $e;
        }
    }

    /**
     * مسح دين البيع للموظف لنفسه عند إلغاء فاتورة بيع بالتقسيط.
     *
     * @param User $user المستخدم الموظف.
     * @param Invoice $invoice الفاتورة الملغاة.
     * @param int|null $companyCashBoxId معرف صندوق النقدية للشركة.
     * @param int|null $userCashBoxId معرف صندوق النقدية للمستخدم (الموظف كعميل).
     * @return void
     * @throws \Throwable
     */
    public function clearSelfSaleDebt(User $user, Invoice $invoice, float $totalPaidInstallments = 0, ?int $companyCashBoxId = null, ?int $userCashBoxId = null): void
    {
        try {
            $companyId = $invoice->company_id ?? $user->company_id;

            // 1. عكس مديونية الفاتورة الأصلية (استرجاع إجمالي المبلغ لصفر الرصيد من هذه الفاتورة)
            $originalTotalAmount = $invoice->installmentPlan->total_amount ?? 0;
            if ($originalTotalAmount > 0) {
                $this->createTransaction(
                    user: $user,
                    type: 'إلغاء مديونية فاتورة (إلغاء شراء لنفسه)',
                    amount: $originalTotalAmount,
                    description: "عكس إجمالي مديونية الفاتورة رقم #{$invoice->invoice_number} بسبب الإلغاء",
                    cashBoxId: $userCashBoxId,
                    companyId: $companyId,
                    invoiceId: $invoice->id,
                    transactionType: 'deposit'
                );
            }

            // 2. رد مبالغ الأقساط التي تم سدادها فعلياً (إن وجدت)
            if ($totalPaidInstallments > 0) {
                $this->createTransaction(
                    user: $user,
                    type: 'رد أقساط مسددة (إلغاء شراء لنفسه)',
                    amount: $totalPaidInstallments,
                    description: "رد مبالغ الأقساط المسددة للفاتورة رقم #{$invoice->invoice_number} بسبب الإلغاء",
                    cashBoxId: $userCashBoxId,
                    companyId: $companyId,
                    invoiceId: $invoice->id,
                    transactionType: 'deposit'
                );
            }
        } catch (\Throwable $e) {
            Log::error('UserSelfDebtService: فشل في مسح دين البيع للموظف لنفسه.', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id, 'user_id' => $user->id]);
            throw $e;
        }
    }

    /**
     * إنشاء سجل معاملة.
     *
     * @param User $user المستخدم المعني.
     * @param string $type نوع المعاملة.
     * @param float $amount المبلغ.
     * @param string $description الوصف.
     * @param int|null $cashBoxId معرف صندوق النقدية.
     * @param int|null $companyId معرف الشركة.
     * @param int|null $invoiceId معرف الفاتورة المرتبطة.
     * @param string $transactionType نوع حركة الرصيد (deposit/withdrawal).
     * @return void
     * @throws \Throwable
     */
    protected function createTransaction(User $user, string $type, float $amount, string $description, ?int $cashBoxId = null, ?int $companyId = null, ?int $invoiceId = null, string $transactionType = 'deposit'): void
    {
        try {
            $balanceBefore = $user->balanceBox($cashBoxId);
            $balanceAfter = ($transactionType === 'deposit') ? ($balanceBefore + $amount) : ($balanceBefore - $amount);

            Transaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'cashbox_id' => $cashBoxId,
                'company_id' => $companyId,
                'created_by' => Auth::id(),
                'invoice_id' => $invoiceId,
            ]);
        } catch (\Throwable $e) {
            Log::error('UserSelfDebtService: فشل إنشاء المعاملة.', ['exception' => $e->getMessage(), 'user_id' => $user->id, 'type' => $type]);
            throw $e;
        }
    }
}
