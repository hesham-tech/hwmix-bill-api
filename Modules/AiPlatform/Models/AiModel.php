<?php

namespace Modules\AiPlatform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * نماذج الذكاء الاصطناعي المتاحة
 */
class AiModel extends Model
{
    protected $table = 'ai_models';

    protected $fillable = [
        'ai_provider_id',
        'model_id',
        'label',
        'version',
        'max_context_tokens',
        'max_output_tokens',
        'input_price_per_1k',
        'output_price_per_1k',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'input_price_per_1k' => 'decimal:4',
        'output_price_per_1k' => 'decimal:4',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(AiModelCapability::class);
    }
}
