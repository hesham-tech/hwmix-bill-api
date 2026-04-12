<?php

namespace App\Services;

use App\Models\User;
use App\Models\Invoice;
use App\Services\AccountingService;
use Illuminate\Support\Facades\Log;

class InvoicePaymentHandler
{
    protected AccountingService $accounting;

    public function __construct(AccountingService $accounting)
    {
        $this->accounting = $accounting;
    }

    /**
     * معالجة دفع فاتورة بيع
     */
    public function handleSalePayment(
        Invoice $invoice,
        User $seller,
        ?User $buyer,
        float $paidAmount,
        float $remainingAmount,
        ?int $sellerCashBoxId = null,
        ?int $buyerCashBoxId = null
    ): void {
        // تحديث الفاتورة أولاً
        if ($paidAmount > 0) {
            $invoice->paid_amount = ($invoice->paid_amount ?? 0) + $paidAmount;
            $invoice->remaining_amount = $invoice->net_amount - $invoice->paid_amount;
        }

        // تسجيل الأثر المحاسبي
        $this->accounting->recordPayment($invoice->company_id, $seller, $buyer, $paidAmount, 'in', [
            'cash_box_id' => $sellerCashBoxId,
            'party_cash_box_id' => $buyerCashBoxId,
            'description' => "تحصيل مبلغ من فاتورة بيع رقم: {$invoice->invoice_number}"
        ]);

        $invoice->updatePaymentStatus();
    }

    /**
     * معالجة دفع فاتورة شراء
     */
    public function handlePurchasePayment(
        Invoice $invoice,
        User $buyer,
        ?User $supplier,
        float $paidAmount,
        float $remainingAmount,
        ?int $buyerCashBoxId = null,
        ?int $supplierCashBoxId = null
    ): void {
        // تحديث الفاتورة
        if ($paidAmount > 0) {
            $invoice->paid_amount = ($invoice->paid_amount ?? 0) + $paidAmount;
            $invoice->remaining_amount = $invoice->net_amount - $invoice->paid_amount;
        }

        // تسجيل الأثر المحاسبي
        $this->accounting->recordPayment($invoice->company_id, $buyer, $supplier, $paidAmount, 'out', [
            'cash_box_id' => $buyerCashBoxId,
            'party_cash_box_id' => $supplierCashBoxId,
            'description' => "سداد مبلغ لفاتورة شراء رقم: {$invoice->invoice_number}"
        ]);

        $invoice->updatePaymentStatus();
    }

    /**
     * عكس المدفوعات عند الإلغاء
     */
    public function reversePayment(
        Invoice $invoice,
        string $invoiceTypeCode,
        User $currentUser,
        ?int $cashBoxId = null,
        ?int $userCashBoxId = null
    ): void {
        $this->accounting->reverseInvoice($invoice, [
            'cash_box_id' => $cashBoxId,
            'user_cash_box_id' => $userCashBoxId
        ]);
        
        $invoice->updatePaymentStatus();
    }
}
