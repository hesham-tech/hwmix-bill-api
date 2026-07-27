<?php

namespace Modules\AiPlatform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مزودو خدمات الذكاء الاصطناعي
 */
class AiProvider extends Model
{
    protected $table = 'ai_providers';

    protected $fillable = [
        'key',
        'label',
        'type',
        'driver_class',
        'base_url',
        'docs_url',
        'logo_url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(AiProviderAccount::class);
    }

    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class);
    }
}
