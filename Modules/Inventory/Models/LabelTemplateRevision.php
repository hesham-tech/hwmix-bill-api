<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

class LabelTemplateRevision extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['design' => 'array', 'published_at' => 'datetime'];

    public function template()
    {
        return $this->belongsTo(LabelTemplate::class, 'label_template_id');
    }
}
