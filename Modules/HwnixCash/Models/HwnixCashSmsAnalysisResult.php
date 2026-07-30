<?php
// موديل يمثل الأصول المحفوظة لنتائج تحليل الرسائل القصيرة واستجابات الذكاء الاصطناعي المنظمة.

namespace Modules\HwnixCash\Models;

use App\Models\Company;
use App\Traits\FilterableByCompany;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HwnixCashSmsAnalysisResult extends Model
{
    use HasFactory, SoftDeletes, FilterableByCompany, LogsActivity;

    protected $table = 'hwnix_cash_sms_analysis_results';

    protected $guarded = ['id'];

    protected $casts = [
        'confidence_score' => 'integer',
        'normalized_json' => 'array',
        'execution_metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(HwnixCashMessage::class, 'message_id');
    }

    public function logLabel(): string
    {
        return "سجل نتيجة تحليل رسالة الذكاء الاصطناعي (#{$this->id}) - ثقة: {$this->confidence_score}%";
    }
}
