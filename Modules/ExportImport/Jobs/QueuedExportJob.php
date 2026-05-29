<?php

namespace Modules\ExportImport\Jobs;

// تعليق عربي: وظيفة خلفية لمعالجة عمليات التصدير الضخمة وتحويل البيانات لملفات CSV مع تتبع التقدم.

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Modules\ExportImport\Models\ExportImportJob;

class QueuedExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $jobId;

    public function __construct(int $jobId)
    {
        $this->jobId = $jobId;
    }

    public function handle(): void
    {
        $exportJob = ExportImportJob::withoutGlobalScopes()->find($this->jobId);

        if (!$exportJob) {
            return;
        }

        try {
            $exportJob->update([
                'status' => 'processing',
                'progress' => 10,
            ]);

            $fileName = 'exports/' . $exportJob->company_id . '/' . strtolower($exportJob->model_type) . '_' . time() . '.csv';
            
            Storage::disk('public')->makeDirectory('exports/' . $exportJob->company_id);
            $filePath = Storage::disk('public')->path($fileName);

            $file = fopen($filePath, 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            if (strtolower($exportJob->model_type) === 'products') {
                fputcsv($file, ['ID', 'الاسم', 'الوصف', 'مفعل']);

                // جلب المنتجات مع عزل الشركة (بدون deleted_at لأن المنتجات لا تستخدم soft deletes)
                $query = \Illuminate\Support\Facades\DB::table('products')
                    ->where('company_id', $exportJob->company_id);

                if ($exportJob->branch_id) {
                    $query->where('branch_id', $exportJob->branch_id);
                }

                $total = $query->count();
                $processed = 0;

                if ($total > 0) {
                    $query->orderBy('id')->chunk(100, function ($products) use ($file, &$processed, $total, $exportJob) {
                        foreach ($products as $product) {
                            fputcsv($file, [
                                $product->id,
                                $product->name,
                                $product->desc ?? '',
                                $product->active ? 'نعم' : 'لا',
                            ]);
                            $processed++;
                        }

                        $progress = 20 + intval(($processed / $total) * 70);
                        $exportJob->update(['progress' => $progress]);
                    });
                } else {
                    $exportJob->update(['progress' => 90]);
                }
            } else {
                fputcsv($file, ['ملاحظة']);
                fputcsv($file, ['النوع المحدد غير مدعوم حالياً للتصدير التلقائي.']);
            }

            fclose($file);

            $exportJob->update([
                'status' => 'completed',
                'progress' => 100,
                'file_path' => $fileName,
            ]);

        } catch (\Exception $e) {
            Log::error('Export job failed: ' . $e->getMessage(), ['job_id' => $this->jobId]);

            $exportJob->update([
                'status' => 'failed',
                'progress' => 0,
                'errors' => [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ],
            ]);

            throw $e;
        }
    }
}
