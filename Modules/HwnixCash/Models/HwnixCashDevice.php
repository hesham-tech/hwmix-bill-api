<?php
// موديل يمثل هواتف الأندرويد المسجلة كبوابة في كاش هونكس HwnixCash.

namespace Modules\HwnixCash\Models;

use App\Models\Company;
use App\Models\User;
use App\Traits\Blameable;
use App\Traits\FilterableByCompany;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class HwnixCashDevice extends Model
{
    use HasFactory, SoftDeletes, FilterableByCompany, Blameable, LogsActivity, Scopes;

    protected $table = 'hwnix_cash_devices';

    protected $guarded = ['id'];

    protected $casts = [
        'capabilities' => 'array',
        'last_seen_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function settings(): HasOne
    {
        return $this->hasOne(HwnixCashDeviceSetting::class, 'sms_device_id');
    }

    public function heartbeats(): HasMany
    {
        return $this->hasMany(HwnixCashDeviceHeartbeat::class, 'sms_device_id');
    }

    public function commands(): HasMany
    {
        return $this->hasMany(HwnixCashDeviceCommand::class, 'sms_device_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(HwnixCashLine::class, 'device_android_id', 'android_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(HwnixCashMessage::class, 'sms_device_id');
    }

    public function isOnline(): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }

        return $this->last_seen_at->diffInMinutes(now()) <= 5;
    }

    public function logLabel(): string
    {
        return "جهاز كاش هونكس ({$this->device_name})";
    }
}
