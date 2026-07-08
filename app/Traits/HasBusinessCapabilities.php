<?php

namespace App\Traits;

/**
 * سمة للتحقق من القدرات والسلوكيات التشغيلية والمالية للطرف بناءً على علاقاته النشطة.
 */
trait HasBusinessCapabilities
{
    // كاش داخلي لتجنب تكرار الاستعلامات خلال نفس طلب الـ API
    protected array $resolvedCapabilities = [];

    /**
     * التحقق من امتلاك المستخدم لقدرة تشغيلية معينة داخل شركة محددة.
     */
    public function hasCapability(string $capabilityCode, ?int $companyId = null): bool
    {
        $companyId = $companyId ?? $this->active_company_id ?? auth()->user()?->active_company_id ?? null;

        if (!$companyId) {
            return false;
        }

        $cacheKey = "{$companyId}_{$capabilityCode}";

        if (array_key_exists($cacheKey, $this->resolvedCapabilities)) {
            return $this->resolvedCapabilities[$cacheKey];
        }

        // التحقق مما إذا كانت علاقة businessRelations محملة بالذاكرة (Eager Loaded) لتجنب N+1 Queries
        if ($this->relationLoaded('businessRelations')) {
            $hasCap = collect($this->businessRelations)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->contains(function ($relation) use ($capabilityCode) {
                    // في حال كانت العلاقة محملة، نتحقق من نوع العلاقة وقدراتها
                    if ($relation->relationLoaded('relationType') && $relation->relationType) {
                        $relationType = $relation->relationType;
                        if ($relationType->relationLoaded('capabilities')) {
                            return collect($relationType->capabilities)->contains('code', $capabilityCode);
                        }
                    }
                    
                    // Fallback في حال عدم تحميل التوابع بالـ Eager load
                    return $relation->relationType()
                        ->whereHas('capabilities', function ($q) use ($capabilityCode) {
                            $q->where('code', $capabilityCode);
                        })
                        ->exists();
                });
        } else {
            // استعلام قاعدة البيانات مباشرة وبسرعة
            $hasCap = $this->businessRelations()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereHas('relationType.capabilities', function ($query) use ($capabilityCode) {
                    $query->where('code', $capabilityCode);
                })
                ->exists();
        }

        return $this->resolvedCapabilities[$cacheKey] = $hasCap;
    }
}
