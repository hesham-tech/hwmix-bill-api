<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * كلاس معالجة وحفظ سجلات النشاط (LogActivityJob) في الخلفية عبر طوابير المهام (Queue).
 * يقوم أيضاً بتشغيل تنظيف تلقائي احتمالي للسجلات القديمة المنتهية الصلاحية لكل شركة.
 */
class LogActivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * تحديث: تعيين الاتصال بنمط sync ليعمل فوراً دون الحاجة لتشغيل queue worker في الاستضافة المشتركة
     */
    protected array $data;

    /**
     * Create a new job instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ActivityLog::create($this->data);

        // تشغيل الحذف الاحتمالي بنسبة 1% لمنع تضخم جداول الأنشطة وسرعة الاستعلامات
        if (isset($this->data['company_id']) && mt_rand(1, 100) === 1) {
            try {
                $company = \App\Models\Company::find($this->data['company_id']);
                if ($company) {
                    $settings = $company->settings;
                    $value = (int)($settings['activity_log_retention_value'] ?? 1);
                    $unit = $settings['activity_log_retention_unit'] ?? 'years';

                    // التأكد من أن وحدة الوقت صالحة لحساب تاريخ القطع
                    if (in_array($unit, ['days', 'months', 'years'])) {
                        $cutoffDate = now()->sub($value, $unit);
                        ActivityLog::where('company_id', $company->id)
                            ->where('created_at', '<', $cutoffDate)
                            ->delete();
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("خطأ أثناء التنظيف التلقائي لسجل النشاط: " . $e->getMessage());
            }
        }
    }
}
