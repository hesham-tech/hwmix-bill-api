<?php

namespace Modules\AiPlatform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * سجلات موجه الذكاء الاصطناعي
 */
class AiRouterLog extends Model
{
    const UPDATED_AT = null;

    protected $table = 'ai_router_logs';

    protected $fillable = [
        'company_id',
        'ai_execution_request_id',
        'capability_key',
        'selected_account_id',
        'selected_model_id',
        'selection_reason',
        'accounts_considered',
        'decision_ms',
        'attempt_number',
    ];

    protected $casts = [
        'accounts_considered' => 'array',
    ];
}
