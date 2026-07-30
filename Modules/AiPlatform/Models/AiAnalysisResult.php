<?php
// موديل يمثل الأصول المعيرة الدائمة لنتائج تحليل الذكاء الاصطناعي المنظم في المنظومة.

namespace Modules\AiPlatform\Models;

use App\Models\Company;
use App\Traits\FilterableByCompany;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiAnalysisResult extends Model
{
    use HasFactory, SoftDeletes, FilterableByCompany, LogsActivity;

    protected $table = 'ai_analysis_results';

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

    public function logLabel(): string
    {
        return "سجل نتيجة تحليل المنصة (#{$this->id}) - نوع: {$this->analysis_type} - ثقة: {$this->confidence_score}%";
    }
}
