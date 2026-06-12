<?php

namespace Modules\Notification\Models;

//   موديل خطوات أتمتة الإشعار لتخزين تفاصيل الإزاحة الزمنية والقوالب وقنوات الإرسال المحددة للخطوة.

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\LogsActivity;

/**
 * موديل خطوات أتمتة الإشعار لتخزين تفاصيل الإزاحة الزمنية والقوالب وقنوات الإرسال المحددة للخطوة مع تتبع الأنشطة.
 */
class NotificationWorkflowStep extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'step_number' => 'integer',
        'delay_days' => 'integer',
        'channel' => 'array', // قائمة القنوات المختارة ['email', 'whatsapp']
    ];

    /**
     * علاقة الخطوة بجدول أتمتة الإشعارات الرئيسي.
     */
    public function workflow()
    {
        return $this->belongsTo(NotificationWorkflow::class, 'workflow_id');
    }

    /**
     * علاقة الخطوة بالقالب المستخدم في الإرسال.
     */
    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }
}
