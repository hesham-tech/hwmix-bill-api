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

        // Fetch ledgers for this day
        $ledgers = \App\Models\FinancialLedger::where('company_id', $companyId)
            ->whereDate('entry_date', $date)
            ->get();

        $revenue = 0;
        $cogs = 0;
        $expenses = 0;
        
        foreach ($ledgers as $ledger) {
            $amount = (float) $ledger->amount;
            if ($ledger->account_type === 'revenue') {
                if ($ledger->type === 'credit') {
                    $revenue += $amount;
                } else {
                    $revenue -= $amount;
                }
            } elseif ($ledger->account_type === 'expense') {
                $isCogs = str_contains($ledger->source_type, 'Invoice');
                if ($isCogs) {
                    if ($ledger->type === 'debit') {
                        $cogs += $amount;
                    } else {
                        $cogs -= $amount;
                    }
                } else {
                    if ($ledger->type === 'debit') {
                        $expenses += $amount;
                    } else {
                        $expenses -= $amount;
                    }
                }
            }
        }

        // Count sales from invoices (only for stats, not financial values)
        $salesCount = \App\Models\Invoice::where('company_id', $companyId)
            ->where(function ($q) use ($date) {
                $q->whereDate('issue_date', $date)
                    ->orWhere(fn($q2) => $q2->whereNull('issue_date')->whereDate('created_at', $date));
            })
            ->whereIn('status', ['confirmed', 'paid', 'partially_paid'])
            ->whereHas('invoiceType', fn($q) => $q->whereIn('code', ['sale', 'installment_sale']))
            ->count();

        $grossProfit = $revenue - $cogs;
        $netProfit = $grossProfit - $expenses;

        \App\Models\DailySalesSummary::updateOrCreate(
            ['date' => \Carbon\Carbon::parse($date)->startOfDay(), 'company_id' => $companyId],
            [
                'total_revenue' => $revenue,
                'sales_count' => $salesCount,
                'total_cogs' => $cogs,
                'total_expenses' => $expenses,
                'gross_profit' => $grossProfit,
                'net_profit' => $netProfit,
            ]
        );

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
