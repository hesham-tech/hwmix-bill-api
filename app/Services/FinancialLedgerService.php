<?php

namespace App\Services;

use App\Models\FinancialLedger;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class FinancialLedgerService
{
    /**
     * Record a balanced double-entry journal.
     */
    public function recordJournalEntry(string $operationId, string $journalEntryId, array $lines, $date = null): void
    {
        // 1. Idempotency Check at Journal Entry level
        $exists = FinancialLedger::withoutGlobalScopes()
            ->where('financial_operation_id', $operationId)
            ->where('journal_entry_id', $journalEntryId)
            ->exists();

        if ($exists) {
            return; // Already recorded
        }

        // 2. Validate Balance
        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            $amt = (float) ($line['amount'] ?? 0);
            if ($amt <= 0) {
                continue; // Skip zero/negative entries
            }
            if ($line['type'] === 'debit') {
                $debit += $amt;
            } else {
                $credit += $amt;
            }
        }

        if (bccomp((string)$debit, (string)$credit, 2) !== 0) {
            throw new \Exception("Unbalanced Journal Entry: Debits ($debit) != Credits ($credit)");
        }

        if ($debit == 0 && $credit == 0) {
            return; // Nothing to record
        }

        // 3. Record Lines
        foreach ($lines as $line) {
            $amt = (float) ($line['amount'] ?? 0);
            if ($amt <= 0) {
                continue;
            }

            FinancialLedger::create([
                'entry_date' => $date ?? now(),
                'type' => $line['type'], // debit or credit
                'amount' => $amt,
                'description' => $line['description'] ?? '',
                'source_type' => $line['source_type'] ?? null,
                'source_id' => $line['source_id'] ?? null,
                'account_type' => $line['account_type'],
                'sub_account_type' => $line['sub_account_type'] ?? null,
                'sub_account_id' => $line['sub_account_id'] ?? null,
                'financial_operation_id' => $operationId,
                'journal_entry_id' => $journalEntryId,
                'company_id' => $line['company_id'] ?? null,
            ]);
        }
    }

    /**
     * تسجيل قيد مالي مفرد (يتم الاستغناء عنه تدريجياً)
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
        ?Carbon $date = null,
        ?string $financialOperationId = null
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
            'financial_operation_id' => $financialOperationId,
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

    /**
     * تسجيل القيود المزدوجة لعمليات الشركاء
     * 
     * @param Model $operation (PartnerOperation)
     */
    public function recordPartnerOperation(Model $operation): void
    {
        $amount = (float) $operation->amount;
        $notes = $operation->notes ? " - {$operation->notes}" : "";
        $partnerName = $operation->partner?->nickname ?? $operation->partner?->name ?? "الشريك #{$operation->partner_id}";
        $date = $operation->operation_date ? \Carbon\Carbon::parse($operation->operation_date) : null;

        switch ($operation->type) {
            case 'capital_increase':
                $this->recordEntry($operation, 'asset', $amount, 'debit', "زيادة رأس مال بالنقدية ({$partnerName}){$notes}", $date);
                $this->recordEntry($operation, 'equity', $amount, 'credit', "إثبات زيادة رأس المال ({$partnerName}){$notes}", $date);
                break;

            case 'capital_withdrawal':
                $this->recordEntry($operation, 'equity', $amount, 'debit', "تخفيض/سحب من رأس المال ({$partnerName}){$notes}", $date);
                $this->recordEntry($operation, 'asset', $amount, 'credit', "صرف مسحوبات من رأس المال ({$partnerName}){$notes}", $date);
                break;

            case 'partner_loan_given':
                $this->recordEntry($operation, 'asset', $amount, 'debit', "استلام قرض من الشريك ({$partnerName}){$notes}", $date);
                $this->recordEntry($operation, 'liability', $amount, 'credit', "إثبات التزام قرض الشريك ({$partnerName}){$notes}", $date);
                break;

            case 'partner_loan_repaid':
                $this->recordEntry($operation, 'liability', $amount, 'debit', "سداد قرض الشريك ({$partnerName}){$notes}", $date);
                $this->recordEntry($operation, 'asset', $amount, 'credit', "صرف سداد قرض الشريك من الخزينة ({$partnerName}){$notes}", $date);
                break;

            case 'profit_distribution':
                $this->recordEntry($operation, 'equity', $amount, 'debit', "توزيع أرباح للشريك ({$partnerName}){$notes}", $date);
                $this->recordEntry($operation, 'asset', $amount, 'credit', "صرف أرباح نقدية للشريك ({$partnerName}){$notes}", $date);
                break;

            case 'loss_coverage':
                $this->recordEntry($operation, 'asset', $amount, 'debit', "استلام نقدية لتغطية خسائر من الشريك ({$partnerName}){$notes}", $date);
                $this->recordEntry($operation, 'equity', $amount, 'credit', "إثبات تغطية الخسائر من الشريك ({$partnerName}){$notes}", $date);
                break;
        }
    }
}
