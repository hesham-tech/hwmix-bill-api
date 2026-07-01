<?php
// موديل يمثل الرسائل الصادرة والواردة وتفاصيل حالات إرسالها ومزامنتها.

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

class SmsMessage extends Model
{
    use HasFactory, Scopes, Blameable, LogsActivity, FilterableByCompany, SoftDeletes;

    protected $table = 'sms_messages';

    protected $fillable = [
        'company_id',
        'created_by',
        'sms_device_id',
        'sms_line_id',
        'phone_number',
        'message_body',
        'direction',
        'status',
        'failure_reason',
        'retry_count',
        'message_ref',
        'sent_at',
        'delivered_at'
    ];

    protected $casts = [
        'retry_count' => 'integer',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function logLabel()
    {
        return "رسالة SMS إلى: {$this->phone_number} (حالة: {$this->status})";
    }

    public function device()
    {
        return $this->belongsTo(SmsDevice::class, 'sms_device_id');
    }

    public function line()
    {
        return $this->belongsTo(SmsLine::class, 'sms_line_id');
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
