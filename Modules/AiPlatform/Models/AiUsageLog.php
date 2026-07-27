<?php

namespace Modules\AiPlatform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * نموذج لتسجيل سجلات الاستخدام واستهلاك التوكينز
 */
class AiUsageLog extends Model
{
    const UPDATED_AT = null;

    protected $guarded = ['id'];
}
