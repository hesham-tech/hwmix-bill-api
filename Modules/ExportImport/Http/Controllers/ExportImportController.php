<?php

namespace Modules\ExportImport\Http\Controllers;

//   متحكم لإدارة وجدولة وتنزيل ملفات التصدير والاستيراد بالخلفية لكل شركة.

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\ExportImport\Models\ExportImportJob;
use Modules\ExportImport\Http\Resources\ExportImportJobResource;
use Modules\ExportImport\Jobs\QueuedExportJob;
use Modules\ExportImport\Jobs\QueuedImportJob;

class ExportImportController extends Controller
{
    /**
     * عرض قائمة بمهام التصدير والاستيراد السابقة والجارية للشركة.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // التحقق من الصلاحية الافتراضية
            if (!Auth::user()->hasPermissionTo(perm_key('products.export')) && !Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('غير مصرح لك باستعراض سجلات التصدير والاستيراد.');
            }

            $jobs = ExportImportJob::orderBy('id', 'desc')->paginate(15);
            return api_success(ExportImportJobResource::collection($jobs), 'تم جلب سجلات التصدير والاستيراد بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * جلب تفاصيل وحالة مهمة تصدير/استيراد محددة.
     */
    public function show($id): JsonResponse
    {
        try {
            $job = ExportImportJob::findOrFail($id);
            return api_success(new ExportImportJobResource($job), 'تم جلب تفاصيل المهمة بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * جدولة عملية تصدير جديدة بالخلفية.
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'model_type' => 'required|string|in:products,invoices,customers'
        ]);

        try {
            $modelType = $request->model_type;

            // التحقق من الصلاحيات حسب نوع الكيان
            if ($modelType === 'products' && !Auth::user()->hasPermissionTo(perm_key('products.export')) && !Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('غير مصرح لك بتصدير المنتجات.');
            }

            // إنشاء سجل المهمة بالـ DB
            $job = ExportImportJob::create([
                'type' => 'export',
                'model_type' => $modelType,
                'status' => 'pending',
                'progress' => 0,
                'company_id' => Auth::user()->active_company_id,
                'branch_id' => Auth::user()->active_branch_id ?? null,
                'created_by' => Auth::id(),
            ]);

            // إطلاق المهمة بالخلفية
            QueuedExportJob::dispatch($job->id);

            return api_success(new ExportImportJobResource($job), 'تم جدولة عملية التصدير بالخلفية بنجاح.', 202);
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * جدولة عملية استيراد جديدة بالخلفية.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'model_type' => 'required|string|in:products',
            'file' => 'required|file|mimes:csv,txt|max:10240' // الحد الأقصى 10 ميجا
        ]);

        try {
            $modelType = $request->model_type;

            // التحقق من الصلاحيات
            if ($modelType === 'products' && !Auth::user()->hasPermissionTo(perm_key('products.import')) && !Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('غير مصرح لك باستيراد المنتجات.');
            }

            // رفع وحفظ الملف مؤقتاً في القرص
            $path = $request->file('file')->store('imports/' . Auth::user()->active_company_id, 'public');

            // إنشاء سجل المهمة بالـ DB
            $job = ExportImportJob::create([
                'type' => 'import',
                'model_type' => $modelType,
                'status' => 'pending',
                'progress' => 0,
                'file_path' => $path,
                'company_id' => Auth::user()->active_company_id,
                'branch_id' => Auth::user()->active_branch_id ?? null,
                'created_by' => Auth::id(),
            ]);

            // إطلاق المهمة بالخلفية
            QueuedImportJob::dispatch($job->id);

            return api_success(new ExportImportJobResource($job), 'تم رفع الملف وجدولة عملية الاستيراد بالخلفية بنجاح.', 202);
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحميل الملف الناتج عن التصدير بشكل آمن.
     */
    public function download($id)
    {
        try {
            // نستخدم withoutGlobalScopes لضمان إمكانية تنزيل الملف بالخلفية أو عبر الروابط المباشرة المؤقتة
            $job = ExportImportJob::withoutGlobalScopes()->findOrFail($id);

            // التحقق من الأمان: يجب أن يتبع الملف نفس شركة المستخدم الموثق
            if (Auth::check() && Auth::user()->active_company_id !== $job->company_id) {
                return abort(403, 'غير مصرح لك بتحميل هذا الملف.');
            }

            if (!$job->file_path || $job->status !== 'completed') {
                return abort(404, 'الملف المطلوب غير جاهز أو غير موجود.');
            }

            if (!Storage::disk('public')->exists($job->file_path)) {
                return abort(404, 'الملف الفعلي غير موجود على خادم التخزين.');
            }

            return Storage::disk('public')->download($job->file_path);
        } catch (\Throwable $e) {
            return abort(500, 'حدث خطأ أثناء محاولة تحميل الملف: ' . $e->getMessage());
        }
    }
}
