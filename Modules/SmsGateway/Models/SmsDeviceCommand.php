<?php
// موديل يمثل الأوامر التشغيلية الموجهة لأجهزة الأندرويد ومتابعة حالات تنفيذها.

namespace Modules\SmsGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SmsDeviceCommand extends Model
{
    use HasFactory;

    protected $table = 'sms_device_commands';

    protected $fillable = [
        'sms_device_id',
        'command_type',
        'payload',
        'status',
        'response_payload',
        'idempotency_key',
        'executed_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'response_payload' => 'array',
        'executed_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(SmsDevice::class, 'sms_device_id');
    }
}
