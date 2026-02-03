<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompanyInvoiceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = \App\Models\Company::all();
        $invoiceTypes = \App\Models\InvoiceType::all();

        if ($companies->isEmpty()) {
            echo "⚠️  No companies found. Please create companies first.\n";
            return;
        }

        if ($invoiceTypes->isEmpty()) {
            echo "⚠️  No invoice types found. Please run InvoiceTypeSeeder first.\n";
            return;
        }

        echo "Syncing {$invoiceTypes->count()} invoice types with {$companies->count()} companies...\n";

        foreach ($companies as $company) {
            $syncData = [];

            foreach ($invoiceTypes as $type) {
                $syncData[$type->id] = ['is_active' => true];
            }

            // استخدام syncWithoutDetaching بدلاً من sync
            // هذا يضيف الأنواع الجديدة فقط دون حذف الموجودة أو التكرار
            $attached = $company->invoiceTypes()->syncWithoutDetaching($syncData);

            $newCount = count($attached['attached'] ?? []);
            $updatedCount = count($attached['updated'] ?? []);

            echo "✓ Company '{$company->name}': {$newCount} new types added, {$updatedCount} existing updated\n";
        }

        echo "\n✅ Done! All companies synchronized with all invoice types.\n";
    }
}
