<?php

namespace App\Observers;

use App\Models\Company;

class CompanyObserver
{
    /**
     * Handle the Company "created" event.
     */
    public function created(Company $company): void
    {
        // ربط كل أنواع الفواتير الموجودة بالشركة الجديدة تلقائياً
        $invoiceTypes = \App\Models\InvoiceType::all();

        $syncData = [];
        foreach ($invoiceTypes as $type) {
            $syncData[$type->id] = ['is_active' => true];
        }

        // استخدام syncWithoutDetaching للأمان (لكن في created لن يكون هناك سجلات موجودة)
        $company->invoiceTypes()->syncWithoutDetaching($syncData);

        // 💳 نسخ طرق الدفع الافتراضية للشركة الجديدة
        $systemMethods = \App\Models\PaymentMethod::where('is_system', true)
            ->whereNull('company_id')
            ->get();

        foreach ($systemMethods as $method) {
            $newMethod = $method->replicate();
            $newMethod->company_id = $company->id;
            $newMethod->is_system = false;
            $newMethod->save();

            // نسخ الصورة إن وجدت
            if ($method->image) {
                $newImage = $method->image->replicate();
                $newImage->imageable_id = $newMethod->id;
                $newImage->save();
            }
        }

        \Log::info("CompanyObserver: Auto-attached {$invoiceTypes->count()} invoice types and copied {$systemMethods->count()} payment methods to company '{$company->name}' (ID: {$company->id})");
    }

    /**
     * Handle the Company "updated" event.
     */
    public function updated(Company $company): void
    {
        //
    }

    /**
     * Handle the Company "deleted" event.
     */
    public function deleted(Company $company): void
    {
        //
    }

    /**
     * Handle the Company "restored" event.
     */
    public function restored(Company $company): void
    {
        //
    }

    /**
     * Handle the Company "force deleted" event.
     */
    public function forceDeleted(Company $company): void
    {
        //
    }
}
