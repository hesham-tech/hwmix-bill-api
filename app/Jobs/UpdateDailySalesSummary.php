<?php

namespace App\Jobs;

use App\Models\DailySalesSummary;
use App\Models\MonthlySalesSummary;
use App\Models\Invoice;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class UpdateDailySalesSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $date;
    protected $companyId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $date, int $companyId)
    {
        $this->date = $date;
        $this->companyId = $companyId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $date = $this->date;
        $companyId = $this->companyId;

        // 1. Calculate Revenue and Sales Count from Invoices
        $invoiceStats = Invoice::query()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($date) {
                $q->whereDate('issue_date', $date)
                    ->orWhere(fn($q2) => $q2->whereNull('issue_date')->whereDate('created_at', $date));
            })
            ->whereIn('status', ['confirmed', 'paid', 'partially_paid'])
            ->whereHas('invoiceType', fn($q) => $q->whereIn('code', ['sale', 'service', 'installment_sale', 'sale_return']))
            ->selectRaw('
                SUM(CASE 
                    WHEN EXISTS (SELECT 1 FROM invoice_types WHERE id = invoices.invoice_type_id AND code = "sale_return") 
                    THEN -net_amount 
                    ELSE net_amount 
                END) as revenue,
                COUNT(*) as count
            ')
            ->first();

        // 2. Calculate COGS from InvoiceItems
        $cogs = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('invoice_types', 'invoices.invoice_type_id', '=', 'invoice_types.id')
            ->where('invoices.company_id', $companyId)
            ->where(function ($q) use ($date) {
                $q->whereDate('invoices.issue_date', $date)
                    ->orWhere(fn($q2) => $q2->whereNull('invoices.issue_date')->whereDate('invoices.created_at', $date));
            })
            ->whereIn('invoices.status', ['confirmed', 'paid', 'partially_paid'])
            ->whereIn('invoice_types.code', ['sale', 'installment_sale', 'sale_return'])
            ->sum(DB::raw('
                CASE 
                    WHEN invoice_types.code = "sale_return" 
                    THEN -invoice_items.total_cost 
                    ELSE invoice_items.total_cost 
                END
            '));

        // 3. Calculate Expenses
        $expenses = Expense::query()
            ->where('company_id', $companyId)
            ->whereDate('expense_date', $date)
            ->sum('amount');

        // 4. Update Daily Summary
        $revenue = (float) ($invoiceStats->revenue ?? 0);
        $totalCogs = (float) $cogs;
        $totalExpenses = (float) $expenses;
        $grossProfit = $revenue - $totalCogs;
        $netProfit = $grossProfit - $totalExpenses;

        DailySalesSummary::updateOrCreate(
            ['date' => $date, 'company_id' => $companyId],
            [
                'total_revenue' => $revenue,
                'sales_count' => $invoiceStats->count ?? 0,
                'total_cogs' => $totalCogs,
                'total_expenses' => $totalExpenses,
                'gross_profit' => $grossProfit,
                'net_profit' => $netProfit,
            ]
        );

        // 5. Trigger Monthly Summary Update
        $this->updateMonthlySummary($date, $companyId);
    }

    /**
     * Update the monthly summary based on daily records.
     */
    protected function updateMonthlySummary(string $date, int $companyId): void
    {
        $carbonDate = Carbon::parse($date);
        $yearMonth = $carbonDate->format('Y-m');

        $monthlyStats = DailySalesSummary::query()
            ->where('company_id', $companyId)
            ->where('date', 'like', "$yearMonth-%")
            ->selectRaw('
                SUM(total_revenue) as revenue,
                SUM(total_cogs) as cogs,
                SUM(total_expenses) as expenses,
                SUM(net_profit) as net_profit,
                SUM(sales_count) as count
            ')
            ->first();

        MonthlySalesSummary::updateOrCreate(
            ['year_month' => $yearMonth, 'company_id' => $companyId],
            [
                'total_revenue' => (float) ($monthlyStats->revenue ?? 0),
                'total_cogs' => (float) ($monthlyStats->cogs ?? 0),
                'total_expenses' => (float) ($monthlyStats->expenses ?? 0),
                'net_profit' => (float) ($monthlyStats->net_profit ?? 0),
                'sales_count' => (int) ($monthlyStats->count ?? 0),
            ]
        );
    }
}
