<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Invoice;
use App\Models\DailySalesSummary;

class DebugBackfill extends Command
{
    protected $signature = 'debug:backfill';
    protected $description = ' Inspect invoice data and summaries ';

    public function handle()
    {
        $this->info('--- Invoice Status Breakdown ---');
        $statuses = DB::table('invoices') // Use DB facade to ignore scopes/soft deletes to see EVERYTHING
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        $this->table(['Status', 'Count'], $statuses->map(fn($s) => [(string) $s->status, $s->total])->toArray());

        $this->info('--- Payment Status Breakdown ---');
        $pStatuses = DB::table('invoices')
            ->select('payment_status', DB::raw('count(*) as total'))
            ->groupBy('payment_status')
            ->get();

        $this->table(['Payment Status', 'Count'], $pStatuses->map(fn($s) => [(string) $s->payment_status, $s->total])->toArray());

        $this->info('--- Daily Sales Summary Count ---');
        $summaryCount = DailySalesSummary::count();
        $this->info("Total Rows: {$summaryCount}");

        if ($summaryCount > 0) {
            $this->info('--- Summary Rows (Date/Revenue) ---');
            $rows = DailySalesSummary::orderBy('date', 'desc')->take(20)->get();
            $this->table(
                ['Date', 'Company', 'Revenue'],
                $rows->map(fn($r) => [$r->date, $r->company_id, $r->total_revenue])->toArray()
            );
        } else {
            $this->warn('Daily Sales Summary table is EMPTY.');
        }

        $this->info('--- Controller Logic Sim (Company 12: Tnatesh) ---');
        $companyId = 12; // Tnatesh Alkowthar
        $currentMonth = now()->format('Y-m');

        $monthlyStats = \App\Models\MonthlySalesSummary::where('company_id', $companyId)
            ->where('year_month', $currentMonth)
            ->first();

        $totalSales = \App\Models\MonthlySalesSummary::where('company_id', $companyId)->sum('total_revenue');

        $this->info("Monthly Sales (Obj): " . json_encode($monthlyStats));
        $this->info("Monthly Revenue: " . ($monthlyStats?->total_revenue ?? 0));
        $this->info("Total Sales (Sum): " . $totalSales);

    }
}
