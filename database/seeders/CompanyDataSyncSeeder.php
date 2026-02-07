<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Warehouse;
use App\Services\CashBoxService;
use Illuminate\Support\Facades\Log;

class CompanyDataSyncSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Global Company Data Sync...');

        // 1. Sync Invoice Types
        $this->command->comment('1/5 Syncing Invoice Types...');
        $this->call(CompanyInvoiceTypeSeeder::class);

        // 2. Sync Payment Methods
        $this->command->comment('2/5 Syncing Payment Methods...');
        $this->call(CompanyPaymentMethodSeeder::class);

        // 3. Sync Cash Box Types
        $this->command->comment('3/5 Syncing Cash Box Types...');
        $this->call(CompanyCashBoxTypeSeeder::class);

        $companies = Company::all();
        $this->command->info("Processing warehouses and cash boxes for {$companies->count()} companies...");

        foreach ($companies as $company) {
            // 4. Ensure Warehouse exists
            $exists = Warehouse::where('company_id', $company->id)->exists();
            if (!$exists) {
                Warehouse::create([
                    'name' => 'المخزن الرئيسي',
                    'company_id' => $company->id,
                    'created_by' => $company->created_by ?? 1,
                    'status' => 'active',
                ]);
                $this->command->info("✓ Created Main Warehouse for: {$company->name}");
            }

            // 5. Ensure Default Cash Boxes exist for all company users
            $companyUsers = CompanyUser::where('company_id', $company->id)->get();
            foreach ($companyUsers as $cu) {
                app(CashBoxService::class)->createDefaultCashBoxForUserCompany(
                    $cu->user_id,
                    $cu->company_id,
                    $cu->created_by ?? 1
                );
            }
            $this->command->info("✓ Verified Cash Boxes for all users in: {$company->name}");
        }

        $this->command->info('✅ Global Company Data Sync Completed Successfully!');
    }
}
