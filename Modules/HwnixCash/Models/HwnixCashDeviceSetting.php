<?php
// موديل يمثل إعدادات وتكشيفات أجهزة كاش هونكس HwnixCash.

namespace Modules\HwnixCash\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HwnixCashDeviceSetting extends Model
{
    use HasFactory;

    protected $table = 'hwnix_cash_device_settings';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'custom_config' => 'array',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(HwnixCashDevice::class, 'sms_device_id');
    }
}
