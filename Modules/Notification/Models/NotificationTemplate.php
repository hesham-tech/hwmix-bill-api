<?php

namespace Modules\Notification\Models;

//   موديل قوالب الإشعارات لتخزين نص الرسائل وقنوات الإرسال المخصصة لكل شركة.

use App\Traits\Blameable;
use App\Traits\FilterableByCompanyOrGlobal;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company;

class NotificationTemplate extends Model
{
    use HasFactory, SoftDeletes, Blameable, Scopes, FilterableByCompanyOrGlobal, LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * علاقة القالب بالشركة الأم.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * ميثود للحصول على تفاصيل المسمى لأغراض السجلات.
     */
    public function logLabel()
    {
        return "قالب إشعارات للشركة: {$this->company?->name} (قالب: {$this->name})";
    }
}
