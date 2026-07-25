<?php
// موديل يمثل سجلات الرسائل القصيرة الصادرة والواردة لموديول كاش هونكس HwnixCash.

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
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HwnixCash\Domain\Enums\SmsMessageStatus;

class HwnixCashMessage extends Model
{
    use HasFactory, SoftDeletes, FilterableByCompany, Blameable, LogsActivity, Scopes;

    protected $table = 'hwnix_cash_messages';

    protected $guarded = ['id'];

    protected $casts = [
        'status' => SmsMessageStatus::class,
        'sent_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(HwnixCashDevice::class, 'sms_device_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(HwnixCashLine::class, 'sms_line_id');
    }

    public function logLabel(): string
    {
        return "رسالة كاش هونكس إلى/من ({$this->phone_number})";
    }
}
