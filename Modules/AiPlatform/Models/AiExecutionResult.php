<?php

namespace Modules\AiPlatform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * نتائج طلبات التنفيذ الخاصة بالذكاء الاصطناعي
 */
class AiExecutionResult extends Model
{
    protected $table = 'ai_execution_results';

    protected $fillable = [
        'ai_execution_request_id',
        'company_id',
        'ai_provider_account_id',
        'ai_model_id',
        'output_data',
        'output_type',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'latency_ms',
        'attempt_number',
        'is_successful',
        'error_code',
        'error_message',
        'tool_calls',
    ];

    protected $casts = [
        'tool_calls' => 'array',
        'is_successful' => 'boolean',
    ];
}
