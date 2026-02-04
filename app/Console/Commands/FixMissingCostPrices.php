<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\InvoiceItem;
use App\Models\Product;

class FixMissingCostPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:fix-costs {--force : Force recalculation of all COGS summaries}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing cost_price in invoice_items from product catalog';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Cost Price Fix...');

        // 1. Update invoice_items where cost_price is missing or 0
        $itemsToFix = InvoiceItem::where(function ($q) {
            $q->whereNull('cost_price')->orWhere('cost_price', 0);
        })->whereNotNull('product_id')->get();

        if ($itemsToFix->isEmpty()) {
            $this->info('No items found with missing cost price.');
        } else {
            $this->info("Found {$itemsToFix->count()} items to fix.");
            $bar = $this->output->createProgressBar($itemsToFix->count());

            foreach ($itemsToFix as $item) {
                // Try to get price from:
                // 1. Specific variant stock cost (Batch Cost)
                // 2. Variant catalog purchase price
                $costPrice = 0;

                if ($item->variant_id) {
                    // 1. Check Stocks
                    $stockCost = DB::table('stocks')
                        ->where('variant_id', $item->variant_id)
                        ->where('cost', '>', 0)
                        ->latest()
                        ->value('cost');

                    if ($stockCost > 0) {
                        $costPrice = $stockCost;
                    } else {
                        // 2. Check Catalog
                        $variant = DB::table('product_variants')->where('id', $item->variant_id)->first(['purchase_price']);
                        if ($variant && $variant->purchase_price > 0) {
                            $costPrice = $variant->purchase_price;
                        }
                    }
                }

                if ($costPrice > 0) {
                    $item->cost_price = $costPrice;
                    $item->total_cost = $item->cost_price * ($item->quantity ?: 1);
                    $item->saveQuietly();
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info('Cost prices backfilled successfully.');
        }

        // 2. Clear summaries to force regeneration if needed
        if ($this->option('force') || !$itemsToFix->isEmpty()) {
            $this->info('Regenerating Sales Summaries to reflect correct costs...');
            $this->call('data:backfill', ['--summaries' => true]);
        }

        $this->info('All done!');
    }
}
