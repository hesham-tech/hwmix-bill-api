<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\StatsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateInvoiceStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $invoice;

    /**
     * Create a new job instance.
     */
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    /**
     * Execute the job.
     */
    public function handle(StatsService $statsService): void
    {
        $invoice = $this->invoice;

        // Idempotency: skip if already aggregated
        if ($invoice->is_aggregated) {
            return;
        }

        // Only aggregate for finalized/sales contexts
        $context = $invoice->invoiceType?->context;
        if (in_array($context, ['sales', 'services'])) {
            $statsService->aggregateInvoiceStats($invoice);

            // Mark as aggregated to prevent double-counting
            $invoice->updateQuietly(['is_aggregated' => true]);
        }
    }
}
