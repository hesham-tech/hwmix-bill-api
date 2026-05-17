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
        $creatorId = $company->created_by ?? \Auth::id();

        if (!$creatorId) {
            \Log::warning("CompanyObserver: تم إنشاء شركة بدون منشئ (ID: {$company->id}). سيتم تخطي تهيئة الموارد الافتراضية.");
            return;
        }

        try {
            // 0️⃣ إنشاء الفرع الرئيسي للشركة
            $branch = \Modules\Companies\Models\Branch::create([
                'name' => 'الفرع الرئيسي',
                'company_id' => $company->id,
                'is_default' => true,
                'created_by' => $creatorId,
            ]);

            // 1️⃣ ربط كل أنواع الفواتير الموجودة بالشركة الجديدة تلقائياً
            $invoiceTypes = \App\Models\InvoiceType::all();
            $syncData = [];
            foreach ($invoiceTypes as $type) {
                $syncData[$type->id] = ['is_active' => true];
            }
            $company->invoiceTypes()->syncWithoutDetaching($syncData);

            // 2️⃣ 💳 نسخ طرق الدفع الافتراضية للشركة الجديدة
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

            // 3️⃣ 📦 إنشاء المخزن الرئيسي وربطه بالفرع
            \Modules\Inventory\Models\Warehouse::create([
                'name' => 'المخزن الرئيسي',
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'created_by' => $creatorId,
                'status' => 'active',
                'is_default' => true,
            ]);

            // 4️⃣ 💰 إنشاء الخزنة الافتراضية لمنشئ الشركة (Creator)
            try {
                app(\App\Services\CashBoxService::class)->createDefaultCashBoxForUserCompany(
                    $creatorId,
                    $company->id,
                    $creatorId
                );
            } catch (\Exception $e) {
                \Log::error("CompanyObserver: فشل إنشاء الخزنة الافتراضية للمنشئ: " . $e->getMessage());
            }

            \Log::info("CompanyObserver: تم تهيئة الشركة '{$company->name}' (ID: {$company->id}) بالكامل بمستودع وطرق دفع وخزنة افتراضية.");
            
        } catch (\Exception $e) {
            \Log::error("CompanyObserver: فشل تهيئة موارد الشركة '{$company->name}': " . $e->getMessage());
        }
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
