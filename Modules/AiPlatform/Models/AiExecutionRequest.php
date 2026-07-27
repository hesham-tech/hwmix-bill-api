<?php

namespace Modules\AiPlatform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * طلبات التنفيذ الموجهة لمحرك الذكاء الاصطناعي
 */
class AiExecutionRequest extends Model
{
    protected $table = 'ai_execution_requests';

    protected $fillable = [
        'company_id',
        'ulid',
        'capability_key',
        'source_type',
        'source_id',
        'ai_agent_id',
        'ai_prompt_id',
        'requested_by',
        'input_data',
        'status',
        'queued',
    ];

    protected $casts = [
        'input_data' => 'array',
        'queued' => 'boolean',
    ];

    public function result(): HasOne
    {
        return $this->hasOne(AiExecutionResult::class);
    }
}
