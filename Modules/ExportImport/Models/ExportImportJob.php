<?php

namespace Modules\ExportImport\Models;

// تعليق عربي: موديل لتتبع تفاصيل وسجلات مهام التصدير والاستيراد مع الدعم الكامل لعزل الشركات والفروع.

use App\Traits\Blameable;
use App\Traits\FilterableByCompany;
use App\Traits\FilterableByBranch;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Companies\Models\Branch;

class ExportImportJob extends Model
{
    use HasFactory, SoftDeletes, Blameable, LogsActivity, Scopes, FilterableByCompany, FilterableByBranch;

    protected $guarded = [];

    protected $casts = [
        'errors' => 'array',
    ];

    /**
     * علاقة المهمة بالفرع.
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
        return "مهمة [{$this->type}] للكيان: {$this->model_type} - الحالة: {$this->status} (التقدم: {$this->progress}%)";
    }
}
