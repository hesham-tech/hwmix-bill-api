<?php
// موديل يمثل جهاز الأندرويد المسجل في النظام كبوابة رسائل.

namespace Modules\SmsGateway\Models;

use App\Models\Company;
use App\Models\User;
use App\Traits\Blameable;
use App\Traits\FilterableByCompany;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SmsDevice extends Model
{
    use HasFactory, Scopes, Blameable, LogsActivity, FilterableByCompany, SoftDeletes;

    protected $table = 'smsg_devices';

    protected $fillable = [
        'company_id',
        'created_by',
        'android_id',
        'uuid',
        'device_name',
        'brand',
        'model',
        'android_version',
        'app_version',
        'capabilities',
        'status',
        'last_seen_at'
    ];

    protected $casts = [
        'capabilities' => 'array',
        'last_seen_at' => 'datetime',
    ];

    public function logLabel()
    {
        return "جهاز رسائل: {$this->device_name} (UUID: {$this->uuid})";
    }

    /**
     * إعدادات الجهاز.
     */
    public function settings()
    {
        return $this->hasOne(SmsDeviceSetting::class, 'sms_device_id');
    }

    /**
     * سجل نبضات الجهاز.
     */
    public function heartbeats()
    {
        return $this->hasMany(SmsDeviceHeartbeat::class, 'sms_device_id');
    }

    /**
     * أوامر الجهاز المعلقة والمنفذة.
     */
    public function commands()
    {
        return $this->hasMany(SmsDeviceCommand::class, 'sms_device_id');
    }

    /**
     * خطوط وشرائح الهاتف.
     */
    public function lines()
    {
        return $this->hasMany(SmsLine::class, 'sms_device_id');
    }

    /**
     * الرسائل المرتبطة بالجهاز.
     */
    public function messages()
    {
        return $this->hasMany(SmsMessage::class, 'sms_device_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
