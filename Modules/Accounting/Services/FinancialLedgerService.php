<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\FinancialLedger;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * خدمة سجل دفتر الأستاذ (FinancialLedgerService) - موديول المحاسبة
 */
class FinancialLedgerService
{
    /**
     * تسجيل قيد مالي في دفتر الأستاذ.
     */
    public function recordEntry(
        Model $source,
        string $accountType,
        float $amount,
        string $type,
        ?string $description = null,
        ?Carbon $date = null
    ): FinancialLedger {
        return FinancialLedger::create([
            'entry_date' => $date ?? now(),
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'source_type' => get_class($source),
            'source_id' => $source->id,
            'account_type' => $accountType,
            'company_id' => $source->company_id ?? Auth::user()->company_id,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * تسجيل العمليات المالية لفاتورة مبيعات
     */
    public function recordSaleInvoice(Model $invoice): void
    {
        $this->recordEntry($invoice, 'revenue', $invoice->net_amount, 'credit', "إيرادات مبيعات - فاتورة رقم: {$invoice->invoice_number}");
        $this->recordEntry($invoice, 'asset', $invoice->net_amount, 'debit', "زيادة أصول (مبيعات) - فاتورة رقم: {$invoice->invoice_number}");
    }

    /**
     * تسجيل العمليات المالية لفاتورة مشتريات
     */
    public function recordPurchaseInvoice(Model $invoice): void
    {
        $this->recordEntry($invoice, 'asset', $invoice->net_amount, 'debit', "زيادة مخزون - فاتورة شراء رقم: {$invoice->invoice_number}");
        $this->recordEntry($invoice, 'asset', $invoice->net_amount, 'credit', "نقص نقدية/زيادة التزامات - شراء فاتورة رقم: {$invoice->invoice_number}");
    }

    /**
     * تسجيل مصروف
     */
    public function recordExpense(Model $expense): void
    {
        $this->recordEntry($expense, 'expense', $expense->amount, 'debit', "إثبات مصروف: {$expense->category?->name} - {$expense->notes}");
        $this->recordEntry($expense, 'asset', $expense->amount, 'credit', "دفع مصروف من الصندوق");
    }

    /**
     * تسجيل العمليات المالية لمرتجع مبيعات
     */
    public function recordSaleReturnInvoice(Model $invoice): void
    {
        $this->recordEntry($invoice, 'revenue', $invoice->net_amount, 'debit', "مرتجع مبيعات - فاتورة رقم: {$invoice->invoice_number}");
        $this->recordEntry($invoice, 'asset', $invoice->net_amount, 'credit', "نقص أصول (رد مبيعات) - فاتورة رقم: {$invoice->invoice_number}");
    }

    /**
     * تسجيل العمليات المالية لمرتجع مشتريات
     */
    public function recordPurchaseReturnInvoice(Model $invoice): void
    {
        $this->recordEntry($invoice, 'asset', $invoice->net_amount, 'credit', "نقص مخزون (مرتجع مشتريات) - فاتورة رقم: {$invoice->invoice_number}");
        $this->recordEntry($invoice, 'asset', $invoice->net_amount, 'debit', "تحصيل نقدية/نقص التزامات - مرتجع شراء رقم: {$invoice->invoice_number}");
    }

    /**
     * تسجيل تكلفة البضاعة المباعة (COGS)
     */
    public function recordCogs(Model $invoice): void
    {
        $totalCost = (float) $invoice->items()->sum('total_cost');

        if ($totalCost <= 0) return;

        $this->recordEntry($invoice, 'expense', $totalCost, 'debit', "تكلفة البضاعة المباعة - فاتورة رقم: {$invoice->invoice_number}");
        $this->recordEntry($invoice, 'asset', $totalCost, 'credit', "نقص المخزون (تكلفة المبيعات) - فاتورة رقم: {$invoice->invoice_number}");
    }
}
