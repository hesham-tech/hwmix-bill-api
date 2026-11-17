<?php

namespace Modules\ExportImport\Http\Controllers;

//   متحكم لإدارة وجدولة وتنزيل ملفات التصدير والاستيراد بالخلفية لكل شركة.

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $user = Auth::user();
        abort_unless($user, 401);

        // Use the authenticated account's company, never an arbitrary request header.
        $companyId = (int) $user->active_company_id;
        abort_unless($companyId > 0, 404);
        // Resolve permissions in the same company used by the download query.
        setPermissionsTeamId($companyId);
        $user->unsetRelation('permissions');
        $user->unsetRelation('roles');
        $isSuper = $user->checkPermissionTo(perm_key('admin.super'));
        abort_unless($isSuper || DB::table('company_user')
            ->where('user_id', $user->id)->where('company_id', $companyId)
            ->where('status', 'active')->exists(), 404);

        // Replace only the header-driven company scope; keep branch and soft-delete scopes.
        $job = ExportImportJob::withoutGlobalScope('company_filter')
            ->where('company_id', $companyId)->where('type', 'export')->findOrFail($id);

        // Products are the only implemented exporter with a defined export permission.
        abort_unless($job->model_type === 'products'
            && ($isSuper || $user->checkPermissionTo(perm_key('products.export'))), 403);

        abort_unless($job->status === 'completed' && $job->file_path, 404);
        $disk = Storage::disk('public');
        abort_unless($disk->exists($job->file_path), 404);

        // Expected 401/403/404 errors must not become a 500 containing exception details.
        return $disk->download($job->file_path, 'products_export_'.$job->id.'.csv', [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
