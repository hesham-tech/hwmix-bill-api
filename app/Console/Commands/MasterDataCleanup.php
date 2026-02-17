<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MasterDataCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:master-data-cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup unused global categories and brands to prevent database bloating.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Master Data Cleanup...');

        // 1. Cleanup Global Categories (no company_id, no products, no children)
        $orphanCategories = \App\Models\Category::whereNull('company_id')
            ->whereDoesntHave('products')
            ->whereDoesntHave('children')
            ->delete();

        $this->info("Deleted {$orphanCategories} orphan global categories.");

        // 2. Cleanup Global Brands (no company_id, no products)
        $orphanBrands = \App\Models\Brand::whereNull('company_id')
            ->whereDoesntHave('products')
            ->delete();

        $this->info("Deleted {$orphanBrands} orphan global brands.");

        $this->info('Cleanup finished.');
    }
}
