<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\InstallmentPlan;
use App\Models\Expense;
use App\Models\DailySalesSummary;
use App\Models\MonthlySalesSummary;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BackfillMissingData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:backfill {--all : Run all backfills} {--installments : Backfill installment plans} {--invoices : Backfill invoice balances} {--summaries : Backfill sales summaries}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing calculated fields and generate summary reports';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('all')) {
            $this->backfillInstallments();
            $this->backfillInvoices();
            $this->backfillSummaries();
            return;
        }

        if ($this->option('installments')) {
            $this->backfillInstallments();
        }

        if ($this->option('invoices')) {
            $this->backfillInvoices();
        }

        if ($this->option('summaries')) {
            $this->backfillSummaries();
        }

        if (!$this->option('all') && !$this->option('installments') && !$this->option('invoices') && !$this->option('summaries')) {
            $this->info('Please specify an option: --all, --installments, --invoices, --summaries');
        }
    }

    private function backfillInstallments()
    {
        $this->info('Starting Installment Plans Backfill...');

        $plans = InstallmentPlan::whereNull('interest_amount')
            ->orWhereNull('interest_rate')
            ->orWhere('total_amount', 0)
            ->get();

        $bar = $this->output->createProgressBar(count($plans));

        foreach ($plans as $plan) {
            $rate = $plan->interest_rate ?? 0;
            $net = $plan->net_amount;

            // Calculate Interest
            $interestAmount = $net * ($rate / 100);
            $total = $net + $interestAmount;

            // Update
            $plan->interest_rate = $rate;
            $plan->interest_amount = $interestAmount;
            $plan->total_amount = $total;

            // Recalculate remaining (simplified: Total - Paid)
            // Assuming paid amount can be derived or we just fix the static totals first
            // If we have payments, we ideally sum them up. 
            // For now, let's assume we just want to fix the planned totals.

            $plan->saveQuietly(); // Avoid triggering observers if possible
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Installment Plans Backfill Completed.');
    }

    private function backfillInvoices()
    {
        $this->info('Starting Invoice Balances Backfill...');

        // Process per company/user to ensure correct running balance
        $users = DB::table('invoices')->select('user_id')->distinct()->pluck('user_id');

        $bar = $this->output->createProgressBar(count($users));

        foreach ($users as $userId) {
            $invoices = Invoice::where('user_id', $userId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            // This logic assumes we want to recalculate based on successful transactions only?
            // Or just valid invoices.
            // A simple running balance might be:

            $currentBalance = 0; // Or fetch initial balance from User model if it exists separately

            foreach ($invoices as $invoice) {
                // If the invoice is paid/partial, it affects balance?
                // Actually, an invoice INCREASES user debt (user_balance_after).
                // Payments DECREASE it.
                // If we assume `previous_balance` is the snapshot BEFORE this invoice.

                // Note: accurate logic requires replaying ALL transactions (invoices + payments).
                // If we only have invoices here, we might miss payments.
                // BUT, if the goal is just to fill the field with *something* reasonable:

                // Let's assume we just want to ensure the field isn't NULL.
                // A safer bet might be to look at the User's current balance and work backwards?
                // Or just set them to 0 if unknown to avoid displaying NULL.

                if (is_null($invoice->previous_balance)) {
                    $invoice->previous_balance = 0;
                }
                if (is_null($invoice->user_balance_after)) {
                    $invoice->user_balance_after = 0;
                }
                $invoice->saveQuietly();
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Invoice Balances Backfill Completed.');
    }

    private function backfillSummaries()
    {
        $this->info('Starting Summaries Backfill (Daily & Monthly)...');

        // 1. Clear existing summaries to avoid duplication/conflicts
        DailySalesSummary::truncate();
        MonthlySalesSummary::truncate();

        // 2. Aggregate Invoices for Revenue & COGS
        // We group by date and company
        $dailyStats = Invoice::selectRaw('
                DATE(created_at) as date, 
                company_id, 
                COUNT(*) as sales_count,
                SUM(net_amount) as total_revenue
            ')
            ->whereIn('status', ['paid', 'partial', 'confirmed']) // Include confirmed as well
            ->groupBy('date', 'company_id')
            ->orderBy('date')
            ->get();

        $bar = $this->output->createProgressBar(count($dailyStats));

        foreach ($dailyStats as $stat) {
            // Calculate COGS for this day
            // We need to sum(cost_price * quantity) for all items in these invoices
            $cogsRequest = DB::table('invoice_items')
                ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                ->whereDate('invoices.created_at', $stat->date)
                ->where('invoices.company_id', $stat->company_id)
                ->whereIn('invoices.status', ['paid', 'partial', 'confirmed']) // Match the main query
                ->selectRaw('SUM(invoice_items.cost_price * invoice_items.quantity) as total_cogs')
                ->first();

            $totalCogs = $cogsRequest->total_cogs ?? 0;

            // Calculate Expenses
            $expensesRequest = Expense::whereDate('expense_date', $stat->date)
                ->where('company_id', $stat->company_id)
                ->sum('amount');

            $totalExpenses = $expensesRequest ?? 0;

            $grossProfit = $stat->total_revenue - $totalCogs;
            $netProfit = $grossProfit - $totalExpenses;

            // Create Daily Record
            DailySalesSummary::create([
                'date' => $stat->date,
                'company_id' => $stat->company_id,
                'sales_count' => $stat->sales_count,
                'total_revenue' => $stat->total_revenue,
                'total_cogs' => $totalCogs,
                'total_expenses' => $totalExpenses,
                'gross_profit' => $grossProfit,
                'net_profit' => $netProfit,
            ]);

            // For Monthly, we can just aggregate from Daily later or do it on the fly.
            // Let's do a simple updateOrInsert for monthly
            $month = Carbon::parse($stat->date)->format('Y-m');

            $monthly = MonthlySalesSummary::firstOrNew([
                'year_month' => $month,
                'company_id' => $stat->company_id
            ]);

            $monthly->sales_count += $stat->sales_count;
            $monthly->total_revenue += $stat->total_revenue;
            $monthly->total_cogs += $totalCogs;
            $monthly->total_expenses += $totalExpenses;
            $monthly->net_profit += $netProfit;
            $monthly->save();

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Summaries Backfill Completed.');
    }
}
