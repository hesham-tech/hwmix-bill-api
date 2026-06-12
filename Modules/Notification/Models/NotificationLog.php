<?php

namespace Modules\Notification\Models;

//   موديل سجل التنبيهات لمراقبة التنبيهات المرسلة وحالتها.

use App\Traits\Blameable;
use App\Traits\FilterableByCompany;
use App\Traits\FilterableByBranch;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Companies\Models\Branch;

class NotificationLog extends Model
{
    use HasFactory, Blameable, LogsActivity, Scopes, FilterableByCompany, FilterableByBranch;

    protected $guarded = [];

    /**
     * علاقة السجل بالفرع.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * ميثود للحصول على تفاصيل المسمى لأغراض السجلات.
     */
    public function logLabel()
    {
        return "سجل تنبيه [{$this->type}] للمستلم: {$this->recipient} - الحالة: {$this->status}";
    }
}
