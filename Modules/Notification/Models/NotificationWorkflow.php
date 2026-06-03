<?php

namespace Modules\Notification\Models;

// تعليق عربي: موديل أتمتة الإشعارات (Workflow) لتخزين تشغيل/تعطيل قواعد الإشعارات المخصصة لكل شركة.

use App\Traits\Blameable;
use App\Traits\FilterableByCompanyOrGlobal;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company;

class NotificationWorkflow extends Model
{
    use HasFactory, SoftDeletes, Blameable, Scopes, FilterableByCompanyOrGlobal, LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * علاقة القاعدة بالشركة الأم.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * علاقة القاعدة بخطوات الإرسال المجدولة.
     */
    public function steps()
    {
        return $this->hasMany(NotificationWorkflowStep::class, 'workflow_id')->orderBy('step_number');
    }

    /**
     * ميثود للحصول على تفاصيل المسمى لأغراض السجلات.
     */
    public function logLabel()
    {
        return "قاعدة أتمتة إشعارات للشركة: {$this->company?->name} (نوع الحدث: {$this->event_type})";
    }
}
