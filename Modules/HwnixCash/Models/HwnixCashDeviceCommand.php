<?php
// موديل يمثل الأوامر البرمجية الموجهة لأجهزة كاش هونكس HwnixCash.

namespace Modules\HwnixCash\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HwnixCash\Domain\Enums\CommandStatus;

class HwnixCashDeviceCommand extends Model
{
    use HasFactory;

    protected $table = 'hwnix_cash_device_commands';

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'response_payload' => 'array',
        'status' => CommandStatus::class,
        'executed_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(HwnixCashDevice::class, 'sms_device_id');
    }
}
