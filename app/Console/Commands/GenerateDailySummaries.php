<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Expense;
use App\Jobs\UpdateDailySalesSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateDailySummaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:generate-daily-summaries {--company= : ID of the company} {--date= : Specific date (YYYY-MM-DD)} {--all : Process all historical data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate daily and monthly financial summaries';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $companyId = $this->option('company');
        $date = $this->option('date');
        $all = $this->option('all');

        if (!$date && !$all) {
            $this->error('Please specify a --date or use --all');
            return;
        }

        $companies = $companyId ? Company::where('id', $companyId)->get() : Company::all();

        foreach ($companies as $company) {
            $this->info("Processing company: {$company->name}");

            if ($date) {
                $this->processDate($date, $company->id);
            } elseif ($all) {
                $this->processAll($company->id);
            }
        }

        $this->info('Summary generation completed!');
    }

    protected function processDate(string $date, int $companyId)
    {
        $this->line("Dispatching update for date: {$date}");
        UpdateDailySalesSummary::dispatchSync($date, $companyId);
    }

    protected function processAll(int $companyId)
    {
        // Get all unique dates from Invoices and Expenses for this company
        $invoiceDatesBase = Invoice::where('company_id', $companyId)
            ->whereNotNull('issue_date')
            ->distinct()
            ->pluck('issue_date');

        $invoiceDatesFallback = Invoice::where('company_id', $companyId)
            ->whereNull('issue_date')
            ->whereNotNull('created_at')
            ->distinct()
            ->pluck('created_at');

        $expenseDates = Expense::where('company_id', $companyId)
            ->whereNotNull('expense_date')
            ->distinct()
            ->pluck('expense_date');

        $dates = $invoiceDatesBase->concat($invoiceDatesFallback)->concat($expenseDates)
            ->map(fn($d) => $d instanceof \Carbon\Carbon ? $d->toDateString() : substr($d, 0, 10))
            ->unique()
            ->sort();

        $this->info("Found " . $dates->count() . " unique dates for company #{$companyId}");

        foreach ($dates as $date) {
            $this->processDate($date, $companyId);
        }
    }
}
