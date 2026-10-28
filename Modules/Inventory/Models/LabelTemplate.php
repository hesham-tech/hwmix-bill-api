<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

class LabelTemplate extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['archived' => 'boolean', 'lock_version' => 'integer'];

    public function revisions()
    {
        return $this->hasMany(LabelTemplateRevision::class);
    }

    public function draft()
    {
        return $this->belongsTo(LabelTemplateRevision::class, 'draft_revision_id');
    }

    public function published()
    {
        return $this->belongsTo(LabelTemplateRevision::class, 'published_revision_id');
    }
}
