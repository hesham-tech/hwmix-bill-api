<?php

namespace Modules\AiPlatform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * يمثل القدرات الأساسية للذكاء الاصطناعي في النظام
 */
class AiCapability extends Model
{
    protected $table = 'ai_capabilities';

    protected $fillable = [
        'key',
        'label',
        'type',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
