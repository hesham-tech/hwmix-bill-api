<?php

namespace Modules\Media\Http\Controllers;

// تعليق عربي: متحكم لإدارة رفع وحذف واستعراض مكتبة الوسائط والصور المخصصة للشركة.

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Media\Models\MediaFile;
use Modules\Media\Http\Requests\UploadMediaRequest;
use Modules\Media\Http\Resources\MediaFileResource;
use Modules\Media\Services\MediaStorageService;

class MediaController extends Controller
{
    protected MediaStorageService $storageService;

    public function __construct(MediaStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * استعراض مكتبة الوسائط الخاصة بالشركة الحالية مع الترقيم.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (!Auth::user()->hasPermissionTo(perm_key('images.view_all')) && !Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('غير مصرح لك باستعراض مكتبة الوسائط.');
            }

            // يتم الفرز scoped بالشركة النشطة تلقائياً بفضل FilterableByCompany
            $mediaFiles = MediaFile::orderBy('id', 'desc')->paginate(18);

            return api_success(MediaFileResource::collection($mediaFiles), 'تم جلب مكتبة الوسائط بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * رفع ملف وسائط جديد (ومعالجته كصورة WebP إن لزم الأمر).
     */
    public function upload(UploadMediaRequest $request): JsonResponse
    {
        try {
            if (!Auth::user()->hasPermissionTo(perm_key('images.create')) && !Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('غير مصرح لك برفع ملفات وسائط.');
            }

            $mediaFile = $this->storageService->store(
                $request->file('file'),
                Auth::user()->active_company_id,
                Auth::id()
            );

            return api_success(new MediaFileResource($mediaFile), 'تم رفع ملف الوسائط بنجاح', 201);
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف ملف وسائط من التخزين وقاعدة البيانات.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $mediaFile = MediaFile::findOrFail($id);

            // التحقق من الصلاحيات
            if (!Auth::user()->hasPermissionTo(perm_key('images.delete_all')) && !Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                // إذا لم يكن لديه حذف الكل، نتحقق مما إذا كان هو من أنشأه ولديه صلاحية حذف الخاص به
                if ($mediaFile->created_by !== Auth::id() || !Auth::user()->hasPermissionTo(perm_key('images.delete_self'))) {
                    return api_forbidden('غير مصرح لك بحذف هذا الملف.');
                }
            }

            $success = $this->storageService->delete($mediaFile);

            if (!$success) {
                return api_error('فشل حذف ملف الوسائط من وحدة التخزين.');
            }

            return api_success(null, 'تم حذف ملف الوسائط بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }
}
