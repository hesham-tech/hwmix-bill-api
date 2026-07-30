<?php

namespace Modules\AiPlatform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * حسابات مزودي الخدمة وإعدادات الوصول ونطاق الحسابات
 */
class AiProviderAccount extends Model
{
    protected $table = 'ai_provider_accounts';

    protected $fillable = [
        'company_id',
        'account_scope', // SYSTEM, COMPANY, SHARED
        'ai_provider_id',
        'label',
        'api_key_encrypted',
        'api_key_hint',
        'api_key_version',
        'custom_base_url',
        'extra_headers',
        'quota_tokens_per_day',
        'quota_tokens_per_month',
        'quota_requests_per_min',
        'used_tokens_today',
        'used_tokens_this_month',
        'priority',
        'is_active',
        'health_status',
        'health_checked_at',
        'last_used_at',
        'expires_at',
        'rotation_reminder_at',
        'failed_attempts',
        'notes',
    ];

    protected $casts = [
        'extra_headers' => 'array',
        'is_active' => 'boolean',
        'health_checked_at' => 'datetime',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'rotation_reminder_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }
}
