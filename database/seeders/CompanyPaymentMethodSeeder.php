<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class CompanyPaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();
        $systemMethods = PaymentMethod::where('is_system', true)
            ->whereNull('company_id')
            ->get();

        if ($systemMethods->isEmpty()) {
            $this->command->warn('No system payment methods found to copy.');
            return;
        }

        foreach ($companies as $company) {
            $count = 0;
            foreach ($systemMethods as $method) {
                // استخدام firstOrCreate بناءً على الكود والشركة لتجنب التكرار
                $exists = PaymentMethod::where('company_id', $company->id)
                    ->where('code', $method->code)
                    ->exists();

                if (!$exists) {
                    $newMethod = $method->replicate();
                    $newMethod->company_id = $company->id;
                    $newMethod->is_system = false;
                    $newMethod->save();

                    // نسخ الصورة إن وجدت لضمان ظهور الشعار لكل شركة
                    if ($method->image) {
                        $newImage = $method->image->replicate();
                        $newImage->imageable_id = $newMethod->id;
                        $newImage->company_id = $company->id;
                        $newImage->save();
                    }
                    $count++;
                }
            }
            if ($count > 0) {
                $this->command->info("Copied {$count} payment methods to company: {$company->name}");
            }
        }
    }
}
