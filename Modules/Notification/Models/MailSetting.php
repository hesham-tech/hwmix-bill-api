<?php

namespace Modules\Notification\Models;

//   موديل إعدادات البريد الإلكتروني لتخزين وحماية تفاصيل SMTP/Mailgun لكل شركة.

use App\Traits\Blameable;
use App\Traits\FilterableByCompanyOrGlobal;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company;

class MailSetting extends Model
{
    use HasFactory, SoftDeletes, Blameable, LogsActivity, Scopes, FilterableByCompanyOrGlobal;

    protected $guarded = [];

    protected $casts = [
        'mail_password' => 'encrypted', // تشفير كلمة السر تلقائياً بفضل لارافيل
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * علاقة الإعدادات بالشركة الأم.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * ميثود للحصول على تفاصيل المسمى لأغراض السجلات.
     */
    public function logLabel()
    {
        return "إعدادات بريد للشركة: {$this->company?->name} ({$this->mail_transport})";
    }
}
