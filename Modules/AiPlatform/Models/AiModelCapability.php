<?php

namespace Modules\AiPlatform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * قدرات كل نموذج ذكاء اصطناعي
 */
class AiModelCapability extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $table = 'ai_model_capabilities';

    protected $fillable = [
        'ai_model_id',
        'ai_capability_key',
    ];
}
