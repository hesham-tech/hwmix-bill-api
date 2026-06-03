<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 */
use App\Traits\LogsActivity;

/**
 * كلاس نموذج الترجمات (Translation) لحفظ وإدارة قيم الحقول المترجمة للكيانات المتعددة اللغات.
 */
class Translation extends Model
{
    use Scopes, Blameable, LogsActivity;
    protected $fillable = ['locale', 'field', 'value'];

    // علاقة Polymorphic
    public function model()
    {
        return $this->morphTo();
    }
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
