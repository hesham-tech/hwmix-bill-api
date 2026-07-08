<?php

namespace Modules\Companies\Models;

// يمثل أنواع العلاقات التجارية المتاحة في النظام مثل عميل، موظف، مورد.

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\RelationTypeObserver;

/**
 * يمثل أنواع العلاقات التجارية المتاحة في النظام مثل عميل، موظف، مورد.
 */
#[ObservedBy([RelationTypeObserver::class])]
class RelationType extends Model
{
    protected $fillable = [
        'code',
        'display_name',
    ];

    /**
     * التحقق من اتساق القدرات المحددة لمنع التعارضات غير المنطقية.
     *
     * @param array $capabilityCodes
     * @throws \InvalidArgumentException
     */
    public static function validateCapabilitiesConsistency(array $capabilityCodes): void
    {
        $hasCashCustody = in_array('has_cash_custody', $capabilityCodes);
        $isInternal = in_array('is_internal', $capabilityCodes);
        $trackReceivable = in_array('track_receivable', $capabilityCodes);
        $trackPayable = in_array('track_payable', $capabilityCodes);

        // 1. تعارض العهدة النقدية مع المستأجر الخارجي
        if ($hasCashCustody && !$isInternal) {
            throw new \InvalidArgumentException('تعارض: لا يمكن تمكين قدرة العهدة النقدية (has_cash_custody) لأطراف خارجية غير داخلية (is_internal = false).');
        }

        // 2. تعارض الذمم الدائنة والمدينة في نفس نوع العلاقة
        if ($trackReceivable && $trackPayable) {
            throw new \InvalidArgumentException('تعارض: لا يمكن لنوع علاقة واحد تتبع الذمم المدينة والدائنة معاً (track_receivable & track_payable). يجب فصلهما في علاقتين.');
        }
    }

    /**
     * القدرات والسلوكيات المرتبطة بنوع العلاقة هذا.
     */
    public function capabilities(): BelongsToMany
    {
        return $this->belongsToMany(Capability::class, 'relation_type_capabilities', 'relation_type_id', 'capability_id');
    }

    /**
     * العلاقات الفردية للمستخدمين من هذا النوع.
     */
    public function businessRelations(): HasMany
    {
        return $this->hasMany(BusinessRelation::class, 'relation_type_id');
    }
}
