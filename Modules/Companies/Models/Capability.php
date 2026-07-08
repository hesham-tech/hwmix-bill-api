<?php

namespace Modules\Companies\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * يمثل القدرات والسلوكيات التشغيلية والمالية مثل امتلاك عهدة أو إمكانية البيع بالآجل.
 */
class Capability extends Model
{
    protected $fillable = [
        'code',
        'display_name',
    ];

    /**
     * أنواع العلاقات المرتبطة بهذه القدرة.
     */
    public function relationTypes(): BelongsToMany
    {
        return $this->belongsToMany(RelationType::class, 'relation_type_capabilities', 'capability_id', 'relation_type_id');
    }
}
