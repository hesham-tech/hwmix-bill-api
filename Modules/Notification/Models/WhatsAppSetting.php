<?php

namespace Modules\Notification\Models;

// تعليق عربي: موديل إعدادات الواتساب لتخزين وحماية تفاصيل الربط بـ Meta Cloud API لكل شركة.

use App\Traits\Blameable;
use App\Traits\FilterableByCompanyOrGlobal;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company;

class WhatsAppSetting extends Model
{
    use HasFactory, SoftDeletes, Blameable, LogsActivity, Scopes, FilterableByCompanyOrGlobal;

    protected $table = 'whatsapp_settings';

    protected $guarded = [];

    protected $casts = [
        'access_token' => 'encrypted', // تشفير رمز الوصول تلقائياً لحمايته في قاعدة البيانات
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
        return "إعدادات واتساب للشركة: {$this->company?->name} (رقم: {$this->phone_number_id})";
    }
}
