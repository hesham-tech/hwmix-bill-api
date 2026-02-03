<?php

namespace App\Services;

use App\Models\FinancialLedger;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class FinancialLedgerService
{
    /**
     * تسجيل قيد مالي في دفتر الأستاذ.
     * 
     * @param Model $source النموذج المصدر (Invoice, Expense, etc.)
     * @param string $accountType نوع الحساب (revenue, expense, asset, liability, equity)
     * @param float $amount المبلغ
     * @param string $type نوع القيد (debit, credit)
     * @param string|null $description الوصف
     * @param Carbon|null $date التاريخ
     * @return FinancialLedger
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
     * تسجيل العمليات المالية لفاتورة مبيعات (قيد مزدوج مبسط)
     * 
     * المدين: الأصول (الصندوق أو ذمم العملاء)
     * الدائن: الإيرادات
     */
    public function recordSaleInvoice(Model $invoice): void
    {
        // 1. الإيرادات (دائن)
        $this->recordEntry(
            $invoice,
            'revenue',
            $invoice->net_amount,
            'credit',
            "إيرادات مبيعات - فاتورة رقم: {$invoice->invoice_number}"
        );

        // 2. الأصول (مدين) - هنا نفترض تبسيطاً أنها تزيد الصندوق أو ذمم العملاء
        $this->recordEntry(
            $invoice,
            'asset',
            $invoice->net_amount,
            'debit',
            "زيادة أصول (مبيعات) - فاتورة رقم: {$invoice->invoice_number}"
        );
    }

    /**
     * تسجيل العمليات المالية لفاتورة مشتريات
     * 
     * المدين: الأصول (المخزون)
     * الدائن: الأصول (الصندوق) أو الخصوم (ذمم الموردين)
     */
    public function recordPurchaseInvoice(Model $invoice): void
    {
        // 1. المخزون (مدين)
        $this->recordEntry(
            $invoice,
            'asset',
            $invoice->net_amount,
            'debit',
            "زيادة مخزون - فاتورة شراء رقم: {$invoice->invoice_number}"
        );

        // 2. الدفع (دائن)
        $this->recordEntry(
            $invoice,
            'asset',
            $invoice->net_amount,
            'credit',
            "نقص نقدية/زيادة التزامات - شراء فاتورة رقم: {$invoice->invoice_number}"
        );
    }

    /**
     * تسجيل مصروف
     * 
     * المدين: المصروفات
     * الدائن: الأصول (النقدية)
     */
    public function recordExpense(Model $expense): void
    {
        // 1. المصروف (مدين)
        $this->recordEntry(
            $expense,
            'expense',
            $expense->amount,
            'debit',
            "إثبات مصروف: {$expense->category?->name} - {$expense->notes}"
        );

        // 2. النقدية (دائن)
        $this->recordEntry(
            $expense,
            'asset',
            $expense->amount,
            'credit',
            "دفع مصروف من الصندوق"
        );
    }

    /**
     * تسجيل العمليات المالية لمرتجع مبيعات
     * 
     * المدين: الإيرادات (مدين لنقصها)
     * الدائن: الأصول (الصندوق أو ذمم العملاء)
     */
    public function recordSaleReturnInvoice(Model $invoice): void
    {
        $this->recordEntry(
            $invoice,
            'revenue',
            $invoice->net_amount,
            'debit',
            "مرتجع مبيعات - فاتورة رقم: {$invoice->invoice_number}"
        );

        $this->recordEntry(
            $invoice,
            'asset',
            $invoice->net_amount,
            'credit',
            "نقص أصول (رد مبيعات) - فاتورة رقم: {$invoice->invoice_number}"
        );
    }

    /**
     * تسجيل العمليات المالية لمرتجع مشتريات
     * 
     * المدين: الأصول (الصندوق) أو الخصوم (نقص ذمة المورد)
     * الدائن: الأصول (المخزون)
     */
    public function recordPurchaseReturnInvoice(Model $invoice): void
    {
        $this->recordEntry(
            $invoice,
            'asset',
            $invoice->net_amount,
            'credit',
            "نقص مخزون (مرتجع مشتريات) - فاتورة رقم: {$invoice->invoice_number}"
        );

        $this->recordEntry(
            $invoice,
            'asset',
            $invoice->net_amount,
            'debit',
            "تحصيل نقدية/نقص التزامات - مرتجع شراء رقم: {$invoice->invoice_number}"
        );
    }

    /**
     * تسجيل تكلفة البضاعة المباعة (COGS)
     * 
     * المدين: المصروفات (تكلفة المبيعات)
     * الدائن: الأصول (المخزون)
     */
    public function recordCogs(Model $invoice): void
    {
        $totalCost = (float) $invoice->items()->sum('total_cost');

        if ($totalCost <= 0)
            return;

        // 1. تكلفة المبيعات (مدين)
        $this->recordEntry(
            $invoice,
            'expense',
            $totalCost,
            'debit',
            "تكلفة البضاعة المباعة - فاتورة رقم: {$invoice->invoice_number}"
        );

        // 2. المخزون (دائن) - نقص في قيمة المخزون
        $this->recordEntry(
            $invoice,
            'asset',
            $totalCost,
            'credit',
            "نقص المخزون (تكلفة المبيعات) - فاتورة رقم: {$invoice->invoice_number}"
        );
    }
}
