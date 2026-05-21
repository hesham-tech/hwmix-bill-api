<?php

namespace Modules\Accounting\Services;

use App\Models\User;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\InvoicePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * خدمة المحاسبة (AccountingService) - موديول المحاسبة
 * تتضمن منطق العزل المالي للعميل النقدي الافتراضي (cash_customer) بحيث لا تُسجَّل
 * أي ذمم أو حركات رصيد باسمه مع الحفاظ الكامل على تدفق الخزينة للمؤسسة.
 */
class AccountingService
{
    /**
     * تسجيل الأثر المالي لإنشاء فاتورة.
     */
    public function recordInvoiceCreation(Invoice $invoice, array $options = []): void
    {
        DB::transaction(function () use ($invoice, $options) {
            $type = $invoice->invoice_type_code;
            $netAmount = (float)$invoice->net_amount;
            $paidAmount = (float)$invoice->paid_amount;
            
            $cashBoxId = $options['cash_box_id'] ?? null;
            $userCashBoxId = $options['user_cash_box_id'] ?? null;
            
            $party = User::withoutGlobalScopes()->find($invoice->user_id);
            $authUser = Auth::user();

            // العميل النقدي الافتراضي: لا تسجيل ذمم أو حركات رصيد على حسابه
            $isCashCustomer = $party && $party->isDefaultCashCustomer($invoice->company_id);

            if (in_array($type, ['sale', 'installment_sale', 'service_invoice'])) {
                // تسجيل المديونية فقط للعملاء الحقيقيين (ليس العميل النقدي)
                if ($party && !$isCashCustomer) {
                    $party->deposit($netAmount, $userCashBoxId, "إثبات مديونية فاتورة {$type} رقم: {$invoice->invoice_number}");
                }

                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'in', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "دفعة من فاتورة رقم: {$invoice->invoice_number}",
                        'skip_party_balance' => $isCashCustomer,
                    ]);

                    $createdById = $authUser ? $authUser->id : $invoice->created_by;

                    // إذا كان العميل حقيقياً وهناك زيادة مدفوعة عن قيمة الفاتورة الصافية
                    if ($paidAmount > $netAmount && $party && !$isCashCustomer) {
                        $excess = $paidAmount - $netAmount;

                        // 1. قصر مدفوع الفاتورة الحالية على قيمتها الصافية فقط
                        $invoice->paid_amount = $netAmount;
                        $invoice->remaining_amount = 0;
                        $invoice->payment_status = Invoice::PAYMENT_PAID;
                        $invoice->save();

                        // تسجيل دفعة للفاتورة الحالية بقيمتها الصافية
                        InvoicePayment::create([
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

                            InvoicePayment::create([
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
                        InvoicePayment::create([
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
                if ($party && !$isCashCustomer) {
                    $party->withdraw($netAmount, $userCashBoxId, "إثبات التزام فاتورة شراء رقم: {$invoice->invoice_number}");
                }

                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'out', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "سداد فاتورة شراء رقم: {$invoice->invoice_number}",
                        'skip_party_balance' => $isCashCustomer,
                    ]);
                }
            } elseif ($type === 'sale_return') {
                if ($party && !$isCashCustomer) {
                    $party->withdraw($netAmount, $userCashBoxId, "إلغاء مديونية (مرتجع مبيعات) رقم: {$invoice->invoice_number}");
                }
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'out', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "رد مبلغ لمرتجع مبيعات رقم: {$invoice->invoice_number}",
                        'skip_party_balance' => $isCashCustomer,
                    ]);
                }
            } elseif ($type === 'purchase_return') {
                if ($party && !$isCashCustomer) {
                    $party->deposit($netAmount, $userCashBoxId, "إلغاء التزام (مرتجع مشتريات) رقم: {$invoice->invoice_number}");
                }
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'in', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "استلام مبلغ لمرتجع مشتريات رقم: {$invoice->invoice_number}",
                        'skip_party_balance' => $isCashCustomer,
                    ]);
                }
            }
        });
    }

    /**
     * تسجيل حركة مالية (قبض أو صرف).
     */
    public function recordPayment(int $companyId, User $staff, ?User $party, float $amount, string $direction, array $options = []): void
    {
        DB::transaction(function () use ($staff, $party, $amount, $direction, $options) {
            $cashBoxId = $options['cash_box_id'] ?? null;
            $partyCashBoxId = $options['party_cash_box_id'] ?? null;
            $description = $options['description'] ?? ($direction === 'in' ? 'قبض نقدي' : 'صرف نقدي');
            // skip_party_balance: يُمرَّر من recordInvoiceCreation عند التعامل مع العميل النقدي
            $skipPartyBalance = $options['skip_party_balance'] ?? false;

            if ($direction === 'in') {
                $staff->deposit($amount, $cashBoxId, $description);
                if ($party && !$skipPartyBalance) {
                    $party->withdraw($amount, $partyCashBoxId, "دفع مبلغ: {$amount} - {$description}");
                }
            } else {
                $staff->withdraw($amount, $cashBoxId, $description);
                if ($party && !$skipPartyBalance) {
                    $party->deposit($amount, $partyCashBoxId, "استلام مبلغ: {$amount} - {$description}");
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
            
            $party = User::withoutGlobalScopes()->find($invoice->user_id);
            $authUser = Auth::user();

            // العميل النقدي الافتراضي: لا عكس لذمم على حسابه
            $isCashCustomer = $party && $party->isDefaultCashCustomer($invoice->company_id);

            if (in_array($type, ['sale', 'installment_sale', 'service_invoice'])) {
                if ($party && !$isCashCustomer) {
                    $party->withdraw($netAmount, $userCashBoxId, "إلغاء مديونية فاتورة {$type} رقم: {$invoice->invoice_number}");
                }
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'out', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "رد مبلغ مدفوع لإلغاء الفاتورة رقم: {$invoice->invoice_number}",
                        'skip_party_balance' => $isCashCustomer,
                    ]);
                }
            } elseif ($type === 'purchase') {
                if ($party && !$isCashCustomer) {
                    $party->deposit($netAmount, $userCashBoxId, "إلغاء التزام فاتورة شراء رقم: {$invoice->invoice_number}");
                }
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'in', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "استرداد مبلغ مدفوع لإلغاء الفاتورة رقم: {$invoice->invoice_number}",
                        'skip_party_balance' => $isCashCustomer,
                    ]);
                }
            } elseif ($type === 'sale_return') {
                if ($party && !$isCashCustomer) {
                    $party->deposit($netAmount, $userCashBoxId, "إلغاء أثر مرتجع مبيعات رقم: {$invoice->invoice_number}");
                }
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'in', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "استرداد مبلغ رد للعميل (إلغاء مرتجع) رقم: {$invoice->invoice_number}",
                        'skip_party_balance' => $isCashCustomer,
                    ]);
                }
            } elseif ($type === 'purchase_return') {
                if ($party && !$isCashCustomer) {
                    $party->withdraw($netAmount, $userCashBoxId, "إلغاء أثر مرتجع مشتريات رقم: {$invoice->invoice_number}");
                }
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'out', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "رد مبلغ استلم من المورد (إلغاء مرتجع) رقم: {$invoice->invoice_number}",
                        'skip_party_balance' => $isCashCustomer,
                    ]);
                }
            }
        });
    }

    /**
     * تحصيل دفعة من طرف (عميل أو مورد) وتوزيعها على الفواتير المتأخرة.
     */
    public function collectPayment(User $staff, User $party, float $amount, array $options = []): void
    {
        DB::transaction(function () use ($staff, $party, $amount, $options) {
            $cashBoxId = $options['cash_box_id'] ?? null;
            $partyCashBoxId = $options['party_cash_box_id'] ?? null;
            $notes = $options['notes'] ?? 'تحصيل دفعة حساب';
            $paymentDate = $options['payment_date'] ?? now();
            $targetInvoiceId = $options['invoice_id'] ?? null;
            $mode = $options['mode'] ?? 'cash';

            if ($mode === 'cash') {
                $staff->deposit($amount, $cashBoxId, "تحصيل نقدي من {$party->name} - {$notes}");
            }

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

                // Use the correct model for InvoicePayment (might be in App\Models or moved)
                InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'cash_box_id' => $cashBoxId,
                    'amount' => $paymentForThisInvoice,
                    'payment_date' => $paymentDate,
                    'notes' => $notes . ($mode === 'balance' ? ' (تسوية من الرصيد)' : ''),
                    'company_id' => $invoice->company_id,
                    'created_by' => $staff->id,
                ]);

                $invoice->paid_amount += $paymentForThisInvoice;
                $invoice->remaining_amount = max(0, $invoice->net_amount - $invoice->paid_amount);
                $invoice->updatePaymentStatus();
                $invoice->save();

                $remaining -= $paymentForThisInvoice;
            }

            $party->withdraw($amount, $partyCashBoxId, "سداد مبلغ: {$amount} - {$notes}");
        });
    }
}
