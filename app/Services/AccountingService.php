<?php

namespace App\Services;

use App\Models\User;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AccountingService
{
    /**
     * تسجيل الأثر المالي لإنشاء فاتورة.
     * 
     * @param Invoice $invoice
     * @param array $options [cash_box_id, user_cash_box_id]
     * @throws \Exception
     */
    public function recordInvoiceCreation(Invoice $invoice, array $options = []): void
    {
        DB::transaction(function () use ($invoice, $options) {
            $type = $invoice->invoice_type_code;
            $netAmount = (float)$invoice->net_amount;
            $paidAmount = (float)$invoice->paid_amount;
            
            $cashBoxId = $options['cash_box_id'] ?? null;
            $userCashBoxId = $options['user_cash_box_id'] ?? null;
            
            $party = User::find($invoice->user_id);
            $authUser = Auth::user();

            if (in_array($type, ['sale', 'installment_sale', 'service_invoice'])) {
                // 1. تسجيل مديونية العميل (Asset +)
                if ($party) {
                    $party->deposit($netAmount, $userCashBoxId, "إثبات مديونية فاتورة {$type} رقم: {$invoice->invoice_number}");
                }

                // 2. إذا كان هناك مبلغ مدفوع
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'in', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "دفعة من فاتورة رقم: {$invoice->invoice_number}"
                    ]);

                    $createdById = $authUser ? $authUser->id : $invoice->created_by;

                    // إذا كان هناك زيادة مدفوعة عن قيمة الفاتورة الصافية
                    if ($paidAmount > $netAmount && $party) {
                        $excess = $paidAmount - $netAmount;

                        // 1. قصر مدفوع الفاتورة الحالية على قيمتها الصافية فقط
                        $invoice->paid_amount = $netAmount;
                        $invoice->remaining_amount = 0;
                        $invoice->payment_status = Invoice::PAYMENT_PAID;
                        $invoice->save();

                        // تسجيل دفعة للفاتورة الحالية بقيمتها الصافية
                        \App\Models\InvoicePayment::create([
                            'invoice_id' => $invoice->id,
                            'cash_box_id' => $cashBoxId,
                            'amount' => $netAmount,
                            'payment_date' => now(),
                            'notes' => "سداد كامل قيمة الفاتورة رقم: {$invoice->invoice_number}",
                            'company_id' => $invoice->company_id,
                            'created_by' => $createdById,
                        ]);

                        // 2. جلب وتوزيع الزيادة على أقدم الفواتير غير المدفوعة للعميل
                        $dueInvoices = Invoice::where('user_id', $party->id)
                            ->where('id', '!=', $invoice->id)
                            ->whereIn('payment_status', [Invoice::PAYMENT_UNPAID, Invoice::PAYMENT_PARTIALLY_PAID])
                            ->where('status', '!=', 'canceled')
                            ->orderBy('id', 'asc')
                            ->lockForUpdate()
                            ->get();

                        /** @var Invoice $dueInvoice */
                        foreach ($dueInvoices as $dueInvoice) {
                            if ($excess <= 0) break;
                            $dueRemaining = (float)$dueInvoice->remaining_amount;
                            if ($dueRemaining <= 0) continue;

                            $allocated = min($excess, $dueRemaining);

                            \App\Models\InvoicePayment::create([
                                'invoice_id' => $dueInvoice->id,
                                'cash_box_id' => $cashBoxId,
                                'amount' => $allocated,
                                'payment_date' => now(),
                                'notes' => "تسوية دفعة زائدة مستلمة من الفاتورة رقم: {$invoice->invoice_number}",
                                'company_id' => $dueInvoice->company_id,
                                'created_by' => $createdById,
                            ]);

                            $dueInvoice->paid_amount += $allocated;
                            $dueInvoice->remaining_amount = max(0, $dueInvoice->net_amount - $dueInvoice->paid_amount);
                            $dueInvoice->updatePaymentStatus();
                            $dueInvoice->save();

                            $excess -= $allocated;
                        }
                    } else {
                        // دفعة عادية مساوية أو أقل من الصافي
                        \App\Models\InvoicePayment::create([
                            'invoice_id' => $invoice->id,
                            'cash_box_id' => $cashBoxId,
                            'amount' => $paidAmount,
                            'payment_date' => now(),
                            'notes' => "دفعة من فاتورة رقم: {$invoice->invoice_number}",
                            'company_id' => $invoice->company_id,
                            'created_by' => $createdById,
                        ]);
                    }
                }
            } elseif ($type === 'purchase') {
                // 1. تسجيل التزام للمورد (Liability -)
                if ($party) {
                    $party->withdraw($netAmount, $userCashBoxId, "إثبات التزام فاتورة شراء رقم: {$invoice->invoice_number}");
                }

                // 2. إذا كان هناك مبلغ مدفوع
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'out', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "سداد فاتورة شراء رقم: {$invoice->invoice_number}"
                    ]);
                }
            } elseif ($type === 'sale_return') {
                // مرتجع مبيعات: تقليل مديونية العميل (Asset -)
                if ($party) {
                    $party->withdraw($netAmount, $userCashBoxId, "إلغاء مديونية (مرتجع مبيعات) رقم: {$invoice->invoice_number}");
                }
                // رد مبلغ نقدي للعميل (Money Out)
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'out', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "رد مبلغ لمرتجع مبيعات رقم: {$invoice->invoice_number}"
                    ]);
                }
            } elseif ($type === 'purchase_return') {
                // مرتجع مشتريات: تقليل التزام المورد (Liability +)
                if ($party) {
                    $party->deposit($netAmount, $userCashBoxId, "إلغاء التزام (مرتجع مشتريات) رقم: {$invoice->invoice_number}");
                }
                // استلام مبلغ نقدي من المورد (Money In)
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'in', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "استلام مبلغ لمرتجع مشتريات رقم: {$invoice->invoice_number}"
                    ]);
                }
            }
        });
    }

    /**
     * تسجيل حركة مالية (قبض أو صرف).
     * 
     * @param int $companyId
     * @param User $staff الموظف المسؤول (الخزنة)
     * @param User|null $party الطرف الآخر (عميل/مورد)
     * @param float $amount
     * @param string $direction (in = توريد للخزنة، out = صرف من الخزنة)
     * @param array $options
     */
    public function recordPayment(int $companyId, User $staff, ?User $party, float $amount, string $direction, array $options = []): void
    {
        DB::transaction(function () use ($companyId, $staff, $party, $amount, $direction, $options) {
            $cashBoxId = $options['cash_box_id'] ?? null;
            $partyCashBoxId = $options['party_cash_box_id'] ?? null;
            $description = $options['description'] ?? ($direction === 'in' ? 'قبض نقدي' : 'صرف نقدي');

            // جلب صناديق النقدية بدون فلاتر الفروع لحساب الأرصدة بدقة
            $staffBox = \App\Models\CashBox::withoutGlobalScopes()
                ->where('id', $cashBoxId ?? 0)
                ->first() ?? \App\Models\CashBox::withoutGlobalScopes()
                ->where('user_id', $staff->id)
                ->where('company_id', $companyId)
                ->where('is_default', true)
                ->first() ?? \App\Models\CashBox::withoutGlobalScopes()
                ->where('user_id', $staff->id)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->first();

            $partyBox = $party ? (\App\Models\CashBox::withoutGlobalScopes()
                ->where('id', $partyCashBoxId ?? 0)
                ->first() ?? \App\Models\CashBox::withoutGlobalScopes()
                ->where('user_id', $party->id)
                ->where('company_id', $companyId)
                ->where('is_default', true)
                ->first() ?? \App\Models\CashBox::withoutGlobalScopes()
                ->where('user_id', $party->id)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->first()) : null;

            // حساب رصيد الموظف قبل وبعد
            $staffBalanceBefore = $staffBox ? (float)$staffBox->balance : 0.00;
            $staffBalanceAfter = $direction === 'in' 
                ? $staffBalanceBefore + $amount 
                : $staffBalanceBefore - $amount;

            // حساب رصيد العميل قبل وبعد
            $partyBalanceBefore = $partyBox ? (float)$partyBox->balance : 0.00;
            $partyBalanceAfter = $direction === 'in' 
                ? $partyBalanceBefore - $amount 
                : $partyBalanceBefore + $amount;

            $logOptions = [
                'employee_balance_before' => $staffBalanceBefore,
                'employee_balance_after' => $staffBalanceAfter,
                'client_balance_before' => $partyBalanceBefore,
                'client_balance_after' => $partyBalanceAfter,
                'source_invoice_id' => $options['invoice_id'] ?? null,
                'source_installment_id' => $options['installment_id'] ?? null,
                'is_transfer' => false,
            ];

            if ($direction === 'in') {
                $staff->deposit($amount, $cashBoxId, $description, true, $logOptions);
                if ($party) {
                    $party->withdraw($amount, $partyCashBoxId, "دفع مبلغ: {$amount} - {$description}", true, $logOptions);
                }
            } else {
                $staff->withdraw($amount, $cashBoxId, $description, true, $logOptions);
                if ($party) {
                    $party->deposit($amount, $partyCashBoxId, "استلام مبلغ: {$amount} - {$description}", true, $logOptions);
                }
            }
        });
    }

    /**
     * عكس الأثر المالي عند إلغاء فاتورة.
     */
    public function reverseInvoice(Invoice $invoice, array $options = []): void
    {
        DB::transaction(function () use ($invoice, $options) {
            $type = $invoice->invoice_type_code;
            $netAmount = (float)$invoice->net_amount;
            $paidAmount = (float)$invoice->paid_amount;
            
            $cashBoxId = $options['cash_box_id'] ?? null;
            $userCashBoxId = $options['user_cash_box_id'] ?? null;
            
            $party = User::find($invoice->user_id);
            $authUser = Auth::user();

            if (in_array($type, ['sale', 'installment_sale', 'service_invoice'])) {
                // 1. عكس مديونية العميل (-)
                if ($party) {
                    $party->withdraw($netAmount, $userCashBoxId, "إلغاء مديونية فاتورة {$type} رقم: {$invoice->invoice_number}");
                }

                // 2. عكس المبلغ المدفوع (صرف من الخزنة لإرجاعه للعميل)
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'out', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "رد مبلغ مدفوع لإلغاء الفاتورة رقم: {$invoice->invoice_number}"
                    ]);
                }
            } elseif ($type === 'purchase') {
                // 1. عكس التزام المورد (+)
                if ($party) {
                    $party->deposit($netAmount, $userCashBoxId, "إلغاء التزام فاتورة شراء رقم: {$invoice->invoice_number}");
                }

                // 2. عكس المبلغ المدفوع (توريد للخزنة لاسترداد ما دفع للمورد)
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'in', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "استرداد مبلغ مدفوع لإلغاء الفاتورة رقم: {$invoice->invoice_number}"
                    ]);
                }
            } elseif ($type === 'sale_return') {
                // عكس مرتجع مبيعات: استعادة مديونية العميل (+)
                if ($party) {
                    $party->deposit($netAmount, $userCashBoxId, "إلغاء أثر مرتجع مبيعات رقم: {$invoice->invoice_number}");
                }
                // استرداد المبلغ الذي رُد للعميل (Money In)
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'in', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "استرداد مبلغ رد للعميل (إلغاء مرتجع) رقم: {$invoice->invoice_number}"
                    ]);
                }
            } elseif ($type === 'purchase_return') {
                // عكس مرتجع مشتريات: استعادة التزام المورد (-)
                if ($party) {
                    $party->withdraw($netAmount, $userCashBoxId, "إلغاء أثر مرتجع مشتريات رقم: {$invoice->invoice_number}");
                }
                // رد المبلغ الذي استُلم من المورد (Money Out)
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'out', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "رد مبلغ استلم من المورد (إلغاء مرتجع) رقم: {$invoice->invoice_number}"
                    ]);
                }
            }
        });
    }

    /**
     * تحصيل دفعة من طرف (عميل أو مورد) وتوزيعها على الفواتير المتأخرة.
     * 
     * @param User $staff الموظف المستلم
     * @param User $party الطرف القائم بالدفع
     * @param float $amount المبلغ
     * @param array $options [cash_box_id, party_cash_box_id, invoice_id, notes, payment_date, mode]
     */
    public function collectPayment(User $staff, User $party, float $amount, array $options = []): void
    {
        DB::transaction(function () use ($staff, $party, $amount, $options) {
            $cashBoxId = $options['cash_box_id'] ?? null;
            $partyCashBoxId = $options['party_cash_box_id'] ?? null;
            $notes = $options['notes'] ?? 'تحصيل دفعة حساب';
            $paymentDate = $options['payment_date'] ?? now();
            $targetInvoiceId = $options['invoice_id'] ?? null;
            $mode = $options['mode'] ?? 'cash'; // 'cash' or 'balance'

            // 1. إذا كان الدفع نقداً، يتم إيداعه في خزنة الموظف (+)
            if ($mode === 'cash') {
                $staff->deposit($amount, $cashBoxId, "تحصيل نقدي من {$party->name} - {$notes}");
            }

            // 2. توزيع المبلغ على الفواتير المستحقة
            $remaining = $amount;
            
            $query = Invoice::where('user_id', $party->id)
                ->whereIn('payment_status', [Invoice::PAYMENT_UNPAID, Invoice::PAYMENT_PARTIALLY_PAID])
                ->where('status', '!=', 'canceled')
                ->orderBy('id', 'asc')
                ->lockForUpdate();

            $dueInvoices = $query->get();

            if ($targetInvoiceId) {
                $selected = $dueInvoices->where('id', $targetInvoiceId)->first();
                if ($selected) {
                    $dueInvoices = $dueInvoices->reject(fn($inv) => $inv->id == $targetInvoiceId)->prepend($selected);
                }
            }

            /** @var Invoice $invoice */
            foreach ($dueInvoices as $invoice) {
                if ($remaining <= 0) break;

                $invoiceRemaining = (float)$invoice->remaining_amount;
                if ($invoiceRemaining <= 0) continue;

                $paymentForThisInvoice = min($remaining, $invoiceRemaining);

                // تسجيل سجل الدفع للفاتورة
                \App\Models\InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'cash_box_id' => $cashBoxId,
                    'amount' => $paymentForThisInvoice,
                    'payment_date' => $paymentDate,
                    'notes' => $notes . ($mode === 'balance' ? ' (تسوية من الرصيد)' : ''),
                    'company_id' => $invoice->company_id,
                    'created_by' => $staff->id,
                ]);

                // تحديث الفاتورة
                $invoice->paid_amount += $paymentForThisInvoice;
                $invoice->remaining_amount = max(0, $invoice->net_amount - $invoice->paid_amount);
                $invoice->updatePaymentStatus();
                $invoice->save();

                $remaining -= $paymentForThisInvoice;
            }

            // 3. تقليل مديونية الطرف (-) بالمقدار الكامل
            // في حالة mode='balance'، هذا يعني أننا نسحب من رصيده (الذي هو أصلاً موجب/أمانات) لسداد فاتورة
            // في حالة mode='cash'، هذا يعني أنه سدد كاش فقلب مديونيته
            $party->withdraw($amount, $partyCashBoxId, "سداد مبلغ: {$amount} - {$notes}");
        });
    }
}
