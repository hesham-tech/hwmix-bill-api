<?php

namespace Modules\ExportImport\Jobs;

// تعليق عربي: وظيفة خلفية لمعالجة استيراد البيانات من ملفات CSV بالخلفية وإدراج السجلات مع تتبع الأخطاء.

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Modules\ExportImport\Models\ExportImportJob;

class QueuedImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $jobId;

    public function __construct(int $jobId)
    {
        $this->jobId = $jobId;
    }

    public function handle(): void
    {
        $importJob = ExportImportJob::withoutGlobalScopes()->find($this->jobId);

        if (!$importJob || !$importJob->file_path) {
            return;
        }

        try {
            $importJob->update([
                'status' => 'processing',
                'progress' => 10,
            ]);

            $filePath = Storage::disk('public')->path($importJob->file_path);

            if (!file_exists($filePath)) {
                throw new \Exception('الملف المرفوع غير موجود في خادم التخزين.');
            }

            $file = fopen($filePath, 'r');
            $bom = fread($file, 3);
            if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) {
                rewind($file);
            }

            $header = fgetcsv($file);
            
            $failedRows = [];
            $successCount = 0;
            $rowCount = 0;

            $totalLines = 0;
            while (!feof($file)) {
                fgets($file);
                $totalLines++;
            }
            rewind($file);
            $bom = fread($file, 3);
            if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) {
                rewind($file);
            }
            fgetcsv($file); // تجاوز العناوين

            if ($totalLines <= 0) {
                $totalLines = 1;
            }

            if (strtolower($importJob->model_type) === 'products') {
                while (($row = fgetcsv($file)) !== false) {
                    $rowCount++;
                    
                    if (count($row) < 2 || empty($row[1])) {
                        $failedRows[] = [
                            'row' => $rowCount,
                            'data' => $row,
                            'reason' => 'اسم المنتج حقل إجباري أو تنسيق السطر خاطئ.'
                        ];
                        continue;
                    }

                    try {
                        DB::transaction(function () use ($row, $importJob) {
                            $name = $row[1];
                            $desc = $row[2] ?? null;
                            $isActive = (isset($row[3]) && ($row[3] === 'نعم' || $row[3] === '1' || $row[3] === 'yes')) ? 1 : 0;
                            
                            // توليد slug فريد
                            $baseSlug = preg_replace('/[^\p{Arabic}a-z0-9\s-]/u', '', strtolower($name));
                            $baseSlug = preg_replace('/\s+/', '-', trim($baseSlug));
                            $slug = $baseSlug ?: 'product';
                            
                            // ضمان تفرد الـ slug عن طريق التحقق في قاعدة البيانات وإضافة معرف فريد
                            $originalSlug = $slug;
                            $i = 1;
                            while (DB::table('products')->where('slug', $slug)->exists()) {
                                $slug = $originalSlug . '-' . $i;
                                $i++;
                            }

                            DB::table('products')->insert([
                                'name' => $name,
                                'slug' => $slug,
                                'desc' => $desc,
                                'active' => $isActive,
                                'featured' => 0,
                                'returnable' => 1,
                                'category_id' => null, // nullable بفضل الهجرات الأخيرة
                                'brand_id' => null,
                                'company_id' => $importJob->company_id,
                                'created_by' => $importJob->created_by,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        });

                        $successCount++;
                    } catch (\Exception $e) {
                        $failedRows[] = [
                            'row' => $rowCount,
                            'data' => $row,
                            'reason' => $e->getMessage()
                        ];
                    }

                    $progress = 10 + intval(($rowCount / $totalLines) * 80);
                    $importJob->update(['progress' => min($progress, 90)]);
                }
            } else {
                throw new \Exception('الكيان المحدد للاستيراد غير مدعوم حالياً.');
            }

            fclose($file);

            $importJob->update([
                'status' => count($failedRows) === $rowCount && $rowCount > 0 ? 'failed' : 'completed',
                'progress' => 100,
                'errors' => [
                    'success_count' => $successCount,
                    'failed_count' => count($failedRows),
                    'failed_details' => $failedRows
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Import job failed: ' . $e->getMessage(), ['job_id' => $this->jobId]);

            $importJob->update([
                'status' => 'failed',
                'progress' => 0,
                'errors' => [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]
            ]);

            throw $e;
        }
    }
}
