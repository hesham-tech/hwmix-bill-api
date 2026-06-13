<?php
// كلاس الخدمة المسؤول عن معالجة تحويلات وحدات القياس الحسابية والتحقق من توافق المجموعات
namespace Modules\Inventory\Services;

use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitConversion;
use Exception;

class UnitConversionService
{
    /**
     * تحويل كمية من وحدة قياس إلى وحدة أخرى.
     *
     * @param float $quantity الكمية المراد تحويلها
     * @param int $fromUnitId معرف الوحدة المصدر
     * @param int $toUnitId معرف الوحدة الهدف
     * @return float الكمية المحسوبة بعد التحويل
     * @throws Exception
     */
    public function convert(float $quantity, int $fromUnitId, int $toUnitId): float
    {
        if ($fromUnitId === $toUnitId) {
            return $quantity;
        }

        $fromUnit = Unit::findOrFail($fromUnitId);
        $toUnit = Unit::findOrFail($toUnitId);

        // التحقق من توافق المجموعة
        if ($fromUnit->unit_group_id !== $toUnit->unit_group_id) {
            throw new Exception("فشل التحويل: لا يمكن التحويل بين وحدات من مجموعات قياس مختلفة ({$fromUnit->name} و {$toUnit->name}).");
        }

        // 1. البحث عن تحويل مباشر
        $directConversion = UnitConversion::where('from_unit_id', $fromUnitId)
            ->where('to_unit_id', $toUnitId)
            ->first();

        if ($directConversion) {
            return $quantity * (float)$directConversion->factor;
        }

        // 2. البحث عن تحويل عكسي مباشر
        $reverseConversion = UnitConversion::where('from_unit_id', $toUnitId)
            ->where('to_unit_id', $fromUnitId)
            ->first();

        if ($reverseConversion) {
            if ((float)$reverseConversion->factor == 0) {
                throw new Exception("فشل التحويل: معامل التحويل يساوي صفر.");
            }
            return $quantity / (float)$reverseConversion->factor;
        }

        // 3. البحث عن مسار غير مباشر (عبر Pivot Unit وسيطة)
        // نجلب جميع التحويلات التي ترتبط بالوحدة المصدر
        $fromRelations = $this->getRelatedConversions($fromUnitId);
        // نجلب جميع التحويلات التي ترتبط بالوحدة الهدف
        $toRelations = $this->getRelatedConversions($toUnitId);

        // نبحث عن وحدة وسيطة مشتركة بين المجموعتين
        $pivotUnitId = null;
        foreach (array_keys($fromRelations) as $uId) {
            if (isset($toRelations[$uId])) {
                $pivotUnitId = $uId;
                break;
            }
        }

        if ($pivotUnitId) {
            // نحول من المصدر إلى الوسيط
            $quantityInPivot = $this->applyRate($quantity, $fromRelations[$pivotUnitId]);
            // نحول من الوسيط إلى الهدف (عكسياً)
            return $this->applyRate($quantityInPivot, $toRelations[$pivotUnitId], true);
        }

        throw new Exception("فشل التحويل: لا توجد قاعدة تحويل معرفة بين الوحدة {$fromUnit->name} والوحدة {$toUnit->name}.");
    }

    /**
     * الحصول على الوحدات المرتبطة مباشرة بوحدة معينة مع معامل التحويل.
     */
    private function getRelatedConversions(int $unitId): array
    {
        $relations = [];

        // تحويلات صادرة من الوحدة
        $outgoing = UnitConversion::where('from_unit_id', $unitId)->get();
        foreach ($outgoing as $conv) {
            $relations[$conv->to_unit_id] = [
                'type' => 'direct',
                'factor' => (float)$conv->factor
            ];
        }

        // تحويلات واردة إلى الوحدة (عكسية)
        $incoming = UnitConversion::where('to_unit_id', $unitId)->get();
        foreach ($incoming as $conv) {
            $relations[$conv->from_unit_id] = [
                'type' => 'reverse',
                'factor' => (float)$conv->factor
            ];
        }

        return $relations;
    }

    /**
     * تطبيق معامل التحويل.
     */
    private function applyRate(float $quantity, array $relation, bool $invert = false): float
    {
        $isReverse = $relation['type'] === 'reverse';
        if ($invert) {
            $isReverse = !$isReverse;
        }

        if ($isReverse) {
            if ($relation['factor'] == 0) {
                throw new Exception("فشل التحويل الحسابي: معامل القسمة يساوي صفر.");
            }
            return $quantity / $relation['factor'];
        }

        return $quantity * $relation['factor'];
    }
}
