<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Inventory\Models\ProductVariant;

/**
 * كلاس أمر Artisan لتحديث النص المفهرس للبحث لجميع متغيرات المنتجات
 */
class UpdateProductVariantsSearchableTextCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:update-searchable-text';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تحديث النص المفهرس للبحث (searchable_text) لجميع متغيرات المنتجات';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('بدء تحديث النص المفهرس للبحث لجميع متغيرات المنتجات...');

        $variants = ProductVariant::withoutGlobalScopes()
            ->with(['product', 'attributes.attribute', 'attributes.attributeValue'])
            ->get();

        $bar = $this->output->createProgressBar($variants->count());
        $bar->start();

        $updatedCount = 0;
        foreach ($variants as $variant) {
            $variant->updateSearchableText(true);
            $updatedCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("تم تحديث {$updatedCount} متغير بنجاح!");

        return Command::SUCCESS;
    }
}
