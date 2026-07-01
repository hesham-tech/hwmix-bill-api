<?php
// موديل يمثل شريحة الاتصال الفعالة داخل منافذ هاتف الأندرويد المسجل.

namespace Modules\SmsGateway\Models;

use App\Models\Company;
use App\Models\User;
use App\Traits\Blameable;
use App\Traits\FilterableByCompany;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SmsLine extends Model
{
    use HasFactory, Scopes, Blameable, FilterableByCompany;

    protected $table = 'sms_gateway_lines';

    protected $fillable = [
        'sms_device_id',
        'company_id',
        'created_by',
        'slot_index',
        'subscription_id',
        'carrier',
        'mcc',
        'mnc',
        'phone_number',
        'network_type',
        'signal_strength',
        'status'
    ];

    protected $casts = [
        'slot_index' => 'integer',
        'signal_strength' => 'integer',
    ];

    public function device()
    {
        return $this->belongsTo(SmsDevice::class, 'sms_device_id');
    }

    public function messages()
    {
        return $this->hasMany(SmsMessage::class, 'sms_line_id');
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
