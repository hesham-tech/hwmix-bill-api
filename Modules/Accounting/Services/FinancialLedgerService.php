<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\FinancialLedger;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * خدمة تسجيل وتسوية قيود دفتر الأستاذ والمعاملات المالية العامة.
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
            'company_id' => $source->company_id ?? Auth::user()->active_company_id,
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
        $this->recordEntry($invoice, 'liability', $invoice->net_amount, 'credit', "إثبات التزام (زيادة التزامات الموردين) - فاتورة شراء رقم: {$invoice->invoice_number}");
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
        $this->recordEntry($invoice, 'liability', $invoice->net_amount, 'debit', "تحصيل نقدية/نقص التزامات (مرتجع مشتريات) - فاتورة رقم: {$invoice->invoice_number}");
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

    /**
     * تسجيل القيود المزدوجة لمعاملات أموال الملاك والشركاء
     * 
     * @param Model $tx (OwnerFundTransaction)
     */
    public function recordOwnerFundTransaction(Model $tx): void
    {
        $amount = (float)$tx->amount;
        $desc = $tx->description ?? "معاملة أملاك من نوع {$tx->type}";

        switch ($tx->type) {
            case 'capital_increase':
                $this->recordEntry($tx, 'asset', $amount, 'debit', "زيادة رأس المال بالنقدية - {$desc}");
                $this->recordEntry($tx, 'equity', $amount, 'credit', "إثبات مساهمة رأس مال جديدة - {$desc}");
                break;
            case 'partner_contribution':
                $this->recordEntry($tx, 'asset', $amount, 'debit', "مساهمة نقدية من شريك - {$desc}");
                $this->recordEntry($tx, 'equity', $amount, 'credit', "إثبات مساهمة جارية من الشريك - {$desc}");
                break;
            case 'loan_from_owner':
                $this->recordEntry($tx, 'asset', $amount, 'debit', "استلام نقدية كقرض من المالك - {$desc}");
                $this->recordEntry($tx, 'liability', $amount, 'credit', "إثبات التزام قرض من المالك - {$desc}");
                break;
            case 'loan_to_owner':
                $this->recordEntry($tx, 'liability', $amount, 'debit', "إثبات ذمة قرض للمالك - {$desc}");
                $this->recordEntry($tx, 'asset', $amount, 'credit', "صرف نقدية كقرض للمالك - {$desc}");
                break;
            case 'advance_from_owner':
                $this->recordEntry($tx, 'asset', $amount, 'debit', "استلام نقدية كسلفة من المالك - {$desc}");
                $this->recordEntry($tx, 'liability', $amount, 'credit', "إثبات التزام سلفة مستحقة للمالك - {$desc}");
                break;
            case 'advance_to_partner':
                $this->recordEntry($tx, 'liability', $amount, 'debit', "إثبات ذمة سلفة للشريك - {$desc}");
                $this->recordEntry($tx, 'asset', $amount, 'credit', "صرف سلفة نقدية للشريك - {$desc}");
                break;
            case 'drawings':
                $this->recordEntry($tx, 'equity', $amount, 'debit', "إثبات مسحوبات نقدية للمالك - {$desc}");
                $this->recordEntry($tx, 'asset', $amount, 'credit', "صرف مسحوبات المالك من الخزينة - {$desc}");
                break;
            case 'profit_distribution':
                $this->recordEntry($tx, 'equity', $amount, 'debit', "توزيع أرباح وتخفيض الأرباح المرحلة - {$desc}");
                $this->recordEntry($tx, 'asset', $amount, 'credit', "صرف أرباح نقدية للملاك - {$desc}");
                break;
        }
    }
}
