<?php

namespace Modules\AiPlatform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * نموذج لتسجيل حركات وتدقيقات النظام الأمني للذكاء الاصطناعي
 */
class AiAuditLog extends Model
{
    const UPDATED_AT = null;

    protected $guarded = ['id'];
}
