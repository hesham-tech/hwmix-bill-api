<?php

namespace App\Observers;

use App\Models\Company;

/**
 * تعليق عربي: مراقب أحداث الشركات للتعامل مع الموارد التلقائية وإدارة المحذوفات.
 */
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
                    $creatorId,
                    $branch->id
                );
            } catch (\Exception $e) {
                \Log::error("CompanyObserver: فشل إنشاء الخزنة الافتراضية للمنشئ: " . $e->getMessage());
            }

            // 5️⃣ 👥 إنشاء العميل النقدي الافتراضي للشركة
            try {
                $company->getOrCreateDefaultCashCustomer();
                \Log::info("CompanyObserver: تم إنشاء العميل النقدي الافتراضي للشركة (ID: {$company->id}).");
            } catch (\Exception $e) {
                \Log::error("CompanyObserver: فشل إنشاء العميل النقدي الافتراضي للشركة: " . $e->getMessage());
            }

            \Log::info("CompanyObserver: تم تهيئة الشركة '{$company->name}' (ID: {$company->id}) بالكامل بمستودع وطرق دفع وخزنة وعميل نقدي افتراضي.");
            
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
     * Handle the Company "deleting" event.
     * تعليق عربي: معالجة فك ارتباط المستخدمين أو حذفهم نهائياً عند الحذف النهائي للشركة.
     */
    public function deleting(Company $company): void
    {
        if ($company->isForceDeleting()) {
            // حذف اللوغو المرتبط بالشركة نهائياً
            if ($logo = $company->images()->where('type', 'logo')->first()) {
                $company->deleteImage($logo);
            }

            // جلب جميع المستخدمين المرتبطين بالشركة
            $associatedUsers = $company->users()->withoutGlobalScopes()->get();
            \Log::info("CompanyObserver: Force deleting company ID {$company->id}. Associated users count: " . $associatedUsers->count());

            foreach ($associatedUsers as $user) {
                // التحقق من وجود شركات أخرى مرتبطة بالمشغل (باستثناء هذه الشركة)
                $otherCompanies = \DB::table('company_user')
                    ->where('user_id', $user->id)
                    ->where('company_id', '!=', $company->id)
                    ->get();

                \Log::info("CompanyObserver: User ID {$user->id}, active_company_id: {$user->active_company_id}. Other companies count: " . $otherCompanies->count());

                if ($otherCompanies->count() > 0) {
                    // إذا كان للمستخدم شركة أخرى، لا نغير الشركة النشطة تلقائياً.
                    // فقط نتركها كما هي، وسيقوم الفرونت اند بمعالجة ذلك عند جلب بيانات المستخدم.
                    \Log::info("CompanyObserver: User ID {$user->id} has other companies. Keeping active_company_id as is.");
                } else {
                    // هذه هي الشركة الوحيدة للمستخدم، نقوم بحذفه نهائياً من النظام
                    // أولاً نحذف سجل الربط بالجدول الوسيط لمنع أي أخطاء قيود
                    \Log::info("CompanyObserver: Deleting User ID {$user->id} permanently as they have no other companies");
                    \DB::table('company_user')->where('user_id', $user->id)->delete();
                    
                    // حذف المستخدم نهائياً
                    $user->newQueryWithoutScopes()->whereKey($user->getKey())->delete();
                }
            }

            // حذف جميع سجلات الربط بالشركة للجميع (لتنظيف الجدول الوسيط)
            \DB::table('company_user')->where('company_id', $company->id)->delete();
        }
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
