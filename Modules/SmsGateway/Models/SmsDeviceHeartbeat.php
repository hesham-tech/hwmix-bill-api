<?php
// موديل يمثل سجل نبضة قلب الجهاز ومؤشراته الحيوية لتسهيل المراقبة.

namespace Modules\SmsGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SmsDeviceHeartbeat extends Model
{
    use HasFactory;

    protected $table = 'sms_gateway_device_heartbeats';

    // استخدام created_at فقط بدون updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'sms_device_id',
        'network_type',
        'battery_level',
        'is_internet_available',
        'free_memory_bytes',
        'free_storage_bytes',
        'app_version',
        'created_at'
    ];

    protected $casts = [
        'is_internet_available' => 'boolean',
        'battery_level' => 'integer',
        'free_memory_bytes' => 'integer',
        'free_storage_bytes' => 'integer',
    ];

    public function device()
    {
        return $this->belongsTo(SmsDevice::class, 'sms_device_id');
    }
}
