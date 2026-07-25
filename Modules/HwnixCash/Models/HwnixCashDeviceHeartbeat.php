<?php
// موديل يمثل نبضات القلب والقياسات الحيوية لأجهزة كاش هونكس HwnixCash.

namespace Modules\HwnixCash\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HwnixCashDeviceHeartbeat extends Model
{
    use HasFactory;

    protected $table = 'hwnix_cash_device_heartbeats';

    protected $guarded = ['id'];

    protected $casts = [
        'is_internet_available' => 'boolean',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(HwnixCashDevice::class, 'sms_device_id');
    }
}
