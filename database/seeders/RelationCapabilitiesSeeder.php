<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Companies\Models\RelationType;
use Modules\Companies\Models\Capability;
use Illuminate\Support\Facades\DB;

/**
 * زارع إعدادات أنواع العلاقات والقدرات التشغيلية وترحيل بيانات العلاقات الحالية.
 */
class RelationCapabilitiesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. زرع أنواع العلاقات الأساسية
        $types = [
            'employee' => 'موظف',
            'customer' => 'عميل',
            'supplier' => 'مورد',
            'driver' => 'سائق توصيل',
            'partner' => 'شريك',
            'technician' => 'فني صيانة',
            'distributor' => 'موزع معتمد',
        ];

        $relationTypeModels = [];
        foreach ($types as $code => $displayName) {
            $relationTypeModels[$code] = RelationType::firstOrCreate(
                ['code' => $code],
                ['display_name' => $displayName]
            );
        }

        // 2. زرع القدرات والسلوكيات التشغيلية والمالية
        $capabilities = [
            'has_cash_custody' => 'امتلاك عهدة مالية (خزنة)',
            'track_receivable' => 'ذمم مدينة للعملاء (البيع الآجل)',
            'track_payable' => 'ذمم دائنة للموردين (الشراء الآجل)',
            'is_internal' => 'مستخدم داخلي (عرض لوحات الموظفين)',
            'calculates_commission' => 'احتساب عمولات مبيعات',
            'can_receive_payments' => 'استلام دفعات مالية',
            'can_issue_invoices' => 'إصدار فواتير للعملاء',
            'can_open_shift' => 'فتح وردية عمل',
            'can_close_shift' => 'إغلاق وردية عمل',
            'can_deliver_orders' => 'توصيل الطلبات',
        ];

        $capabilityModels = [];
        foreach ($capabilities as $code => $displayName) {
            $capabilityModels[$code] = Capability::firstOrCreate(
                ['code' => $code],
                ['display_name' => $displayName]
            );
        }

        // 3. ربط العلاقات بالقدرات (Pivot)
        // الموظف
        $relationTypeModels['employee']->capabilities()->syncWithoutDetaching([
            $capabilityModels['has_cash_custody']->id,
            $capabilityModels['is_internal']->id,
            $capabilityModels['can_receive_payments']->id,
            $capabilityModels['can_open_shift']->id,
            $capabilityModels['can_close_shift']->id,
        ]);

        // العميل
        $relationTypeModels['customer']->capabilities()->syncWithoutDetaching([
            $capabilityModels['track_receivable']->id,
        ]);

        // المورد
        $relationTypeModels['supplier']->capabilities()->syncWithoutDetaching([
            $capabilityModels['track_payable']->id,
        ]);

        // السائق
        $relationTypeModels['driver']->capabilities()->syncWithoutDetaching([
            $capabilityModels['has_cash_custody']->id,
            $capabilityModels['is_internal']->id,
            $capabilityModels['can_deliver_orders']->id,
        ]);

        // الموزع
        $relationTypeModels['distributor']->capabilities()->syncWithoutDetaching([
            $capabilityModels['track_receivable']->id,
        ]);

        // 4. ترحيل بيانات العلاقات الحالية (Data Migration)
        if (isset($this->command)) {
            $this->command->info('جاري ترحيل بيانات العلاقات التجارية القديمة...');
        }
        
        $migratedCount = 0;
        DB::table('business_relations')->chunkById(100, function ($relations) use (&$migratedCount, $relationTypeModels) {
            foreach ($relations as $rel) {
                // جلب الكود النصي القديم
                $oldCode = $rel->relation_type;
                
                // تحديد النوع المطابق
                $typeModel = $relationTypeModels[$oldCode] ?? null;
                
                if ($typeModel) {
                    DB::table('business_relations')
                        ->where('id', $rel->id)
                        ->update(['relation_type_id' => $typeModel->id]);
                    $migratedCount++;
                }
            }
        });

        if (isset($this->command)) {
            $this->command->info("تم ترحيل عدد {$migratedCount} علاقة تجارية بنجاح.");
        }
    }
}
