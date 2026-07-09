<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use App\Models\InvoicePayment;
use Illuminate\Support\Facades\Auth;
use Exception;

/**
 * خدمة إدارة مشتقات الفواتير وتحديث دفعاتها وحالتها المالية.
 */
class InvoiceStateService
{
    /**
     * تسجيل دفعة جديدة للفاتورة وإعادة احتساب حالتها المالية ديناميكياً
     */
    public function recordPayment(Model $invoice, float $amount, string $operationId, array $metadata = []): void
    {
        $companyId = $metadata['company_id'] ?? $invoice->company_id;
        $createdById = Auth::id() ?? $metadata['created_by'] ?? $invoice->created_by;

        // تسجيل الدفعة وربطها بالعملية المالية
        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'cash_box_id' => $metadata['cash_box_id'] ?? null,
            'amount' => $amount,
            'payment_date' => $metadata['payment_date'] ?? now(),
            'notes' => $metadata['description'] ?? "دفعة مسجلة بقيمة {$amount}",
            'company_id' => $companyId,
            'created_by' => $createdById,
            'financial_operation_id' => $operationId,
        ]);

        // إعادة احتساب الأرصدة التراكمية ديناميكياً من جدول الدفعات لضمان عدم وجود تكرار
        $totalPaid = (float) InvoicePayment::where('invoice_id', $invoice->id)->sum('amount');

        // تطبيق invariant القوانين الحسابية الصارمة
        if ($totalPaid < 0) {
            throw new Exception("لا يمكن أن يصبح المدفوع سالباً.");
        }

        $netAmount = (float)$invoice->net_amount;

        $invoice->paid_amount = $totalPaid;
        $invoice->remaining_amount = max(0.00, $netAmount - $totalPaid);

        // اشتقاق الحالة المالية تلقائياً
        if ($totalPaid == 0) {
            $invoice->payment_status = $invoice::PAYMENT_UNPAID;
            $invoice->status = $invoice::STATUS_CONFIRMED;
        } elseif ($totalPaid >= $netAmount) {
            $invoice->payment_status = $invoice::PAYMENT_PAID;
            $invoice->status = $invoice::STATUS_PAID;
        } else {
            $invoice->payment_status = $invoice::PAYMENT_PARTIALLY_PAID;
            $invoice->status = $invoice::STATUS_PARTIALLY_PAID;
        }

        $invoice->save();
    }

    /**
     * إلغاء جميع الدفعات التابعة لعملية مالية معينة وإعادة حساب حالة الفاتورة
     */
    public function cancelPayments(Model $invoice, string $operationId): void
    {
        // حذف دفعات الفاتورة التابعة للعملية بشكل ناعم أو كامل (تاريخياً تماشياً مع الدستور)
        // وبما أنه إلغاء للعملية بالكامل، فسنقوم بإعادة احتساب مدفوع الفاتورة الفعلي
        $totalPaid = (float) InvoicePayment::where('invoice_id', $invoice->id)
            ->where('financial_operation_id', '!=', $operationId)
            ->sum('amount');

        $netAmount = (float)$invoice->net_amount;

        $invoice->paid_amount = $totalPaid;
        $invoice->remaining_amount = max(0.00, $netAmount - $totalPaid);

        if ($totalPaid == 0) {
            $invoice->payment_status = $invoice::PAYMENT_UNPAID;
            $invoice->status = $invoice::STATUS_CONFIRMED;
        } elseif ($totalPaid >= $netAmount) {
            $invoice->payment_status = $invoice::PAYMENT_PAID;
            $invoice->status = $invoice::STATUS_PAID;
        } else {
            $invoice->payment_status = $invoice::PAYMENT_PARTIALLY_PAID;
            $invoice->status = $invoice::STATUS_PARTIALLY_PAID;
        }

        $invoice->save();
    }
}
