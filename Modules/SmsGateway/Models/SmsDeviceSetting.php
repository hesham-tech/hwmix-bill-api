<?php
// موديل يمثل إعدادات تشغيل جهاز بوابة الرسائل وعقود الإصدارات والـ Feature Flags.

namespace Modules\SmsGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SmsDeviceSetting extends Model
{
    use HasFactory;

    protected $table = 'smsgate_device_settings';

    protected $fillable = [
        'sms_device_id',
        'configuration_version',
        'polling_interval_seconds',
        'max_retry_count',
        'logging_level',
        'feature_flags',
        'sync_limits'
    ];

    protected $casts = [
        'feature_flags' => 'array',
        'sync_limits' => 'array',
        'configuration_version' => 'integer',
        'polling_interval_seconds' => 'integer',
        'max_retry_count' => 'integer',
    ];

    public function device()
    {
        return $this->belongsTo(SmsDevice::class, 'sms_device_id');
    }
}
