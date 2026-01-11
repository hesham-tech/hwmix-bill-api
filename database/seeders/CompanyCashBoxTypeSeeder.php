<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CashBoxType;
use Illuminate\Database\Seeder;

class CompanyCashBoxTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();
        $templateTypes = CashBoxType::whereNull('company_id')->get();

        if ($templateTypes->isEmpty()) {
            $this->command->warn('No template cash box types found to copy.');
            return;
        }

        foreach ($companies as $company) {
            $count = 0;
            foreach ($templateTypes as $type) {
                // Check if already exists for this company
                $exists = CashBoxType::where('company_id', $company->id)
                    ->where('name', $type->name)
                    ->exists();

                if (!$exists) {
                    $newType = $type->replicate();
                    $newType->company_id = $company->id;
                    $newType->is_system = false; // System status only for templates or special case
                    $newType->save();
                    $count++;
                }
            }
            if ($count > 0) {
                $this->command->info("Copied {$count} cash box types to company: {$company->name}");
            }
        }
    }
}
