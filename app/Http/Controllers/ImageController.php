<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage; // إضافة Facade التخزين
use App\Http\Resources\Image\ImageResource;
use App\Services\ImageService;

class ImageController extends Controller
{
    /**
     * عرض صور المستخدم
     */
    public function index(Request $request)
    {
        try {
            $query = Image::query()->where('created_by', Auth::id());

            if ($request->filled('linked')) {
                if ($request->linked === '1') {
                    $query->whereNotNull('imageable_id')->where('is_temp', false);
                } else {
                    $query->whereNull('imageable_id')->where('is_temp', true);
                }
            }

            if ($request->filled('is_temp') && in_array($request->is_temp, ['0', '1'], true)) {
                $query->where('is_temp', $request->is_temp);
            }

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            $images = $query->latest()->get();

            return api_success(ImageResource::collection($images), 'تم جلب الصور');
        } catch (Throwable $e) {
            // تسجيل الخطأ كاملاً لأغراض التصحيح
            logger()->error("خطأ أثناء جلب الصور: " . $e->getMessage() . " في الملف: " . $e->getFile() . " السطر: " . $e->getLine());
            return api_exception($e, 500, 'خطأ أثناء جلب الصور');
        }
    }

    /**
     * رفع صور جديدة (مؤقتة)
     */
    public function store(Request $request)
    {
        try {
            // سجل جميع بيانات الطلب
            logger($request->all());
            // سجل بيانات ملفات الصور
            logger($request->file('images'));

            $request->validate([
                'images' => ['required', 'array'],
                'images.*' => ['image', 'max:5120'], // 5 ميجابايت كحد أقصى
                'type' => ['nullable', 'string'],
            ]);

            $user = Auth::user();
            $companyId = $user->company_id;
            $type = $request->input('type', 'misc');
            $uploadedImages = [];

            foreach ($request->file('images') as $file) {
                // اسم الملف الفريد
                $fileName = "temp_{$user->id}_" . uniqid() . '.' . $file->getClientOriginalExtension();

                // تخزين الملف في مجلد مؤقت خاص بالشركة
                // المسار: uploads/{company_id}/temp
                $path = $file->storeAs("uploads/{$companyId}/temp", $fileName, 'public');

                // URL العام للملف المخزن
                $url = Storage::url($path);

                $image = Image::create([
                    'url' => $url,
                    'type' => $type,
                    'company_id' => $companyId,
                    'created_by' => $user->id,
                    'is_temp' => 1, // تأكيد أنها صورة مؤقتة
                ]);

                $uploadedImages[] = $image;
            }

            return api_success(ImageResource::collection($uploadedImages), 'تم رفع الصور بنجاح');
        } catch (Throwable $e) {
            // تسجيل الخطأ كاملاً لأغراض التصحيح
            logger()->error("خطأ أثناء رفع الصور: " . $e->getMessage() . " في الملف: " . $e->getFile() . " السطر: " . $e->getLine());
            return api_exception($e, 500, 'خطأ أثناء رفع الصور');
        }
    }

    /**
     * تعديل بيانات صورة
     */
    public function update(Request $request, Image $image)
    {
        try {
            if ($image->created_by !== Auth::id()) {
                return api_error('غير مصرح لك بتعديل هذه الصورة', [], 403);
            }

            $request->validate([
                'type' => ['nullable', 'string'],
            ]);

            $image->update($request->only('type'));

            return api_success(new ImageResource($image), 'تم تحديث الصورة');
        } catch (Throwable $e) {
            // تسجيل الخطأ كاملاً لأغراض التصحيح
            logger()->error("خطأ أثناء تحديث الصورة: " . $e->getMessage() . " في الملف: " . $e->getFile() . " السطر: " . $e->getLine());
            return api_exception($e, 500, 'خطأ أثناء تحديث الصورة');
        }
    }

    /**
     * تعيين الصورة كصورة أساسية فوراً
     */
    public function setPrimary(Image $image)
    {
        try {
            if (!$image->imageable_id || !$image->imageable_type) {
                return api_error('هذه الصورة غير مرتبطة بمنتج أو متغير للتغيير.', [], 400);
            }

            $model = $image->imageable;
            if (!$model) {
                return api_error('مورد الصورة غير موجود.', [], 404);
            }

            // جلب كل IDs الصور المرتبطة بهذا الموديل
            $imageIds = Image::where('imageable_type', $image->imageable_type)
                ->where('imageable_id', $image->imageable_id)
                ->pluck('id')
                ->toArray();

            ImageService::handlePrimaryImage($model, $imageIds, $image->id);

            return api_success(new ImageResource($image), 'تم تعيين الصورة كصورة أساسية بنجاح');
        } catch (Throwable $e) {
            logger()->error("خطأ أثناء تعيين الصورة الأساسية: " . $e->getMessage());
            return api_exception($e, 500, 'خطأ أثناء تعيين الصورة الأساسية');
        }
    }

    /**
     * حذف مجموعة صور
     */
    public function destroy(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'integer|exists:images,id',
            ]);

            $userId = Auth::id();

            $imageIds = Image::whereIn('id', $request->ids)
                ->where('created_by', $userId)
                ->pluck('id')
                ->toArray();

            if (empty($imageIds)) {
                return api_error('لا توجد صور مسموح بحذفها.', [], 403);
            }

            ImageService::deleteImages($imageIds);

            return api_success([], 'تم حذف الصور بنجاح');
        } catch (Throwable $e) {
            // تسجيل الخطأ كاملاً لأغراض التصحيح
            logger()->error("خطأ أثناء حذف الصور: " . $e->getMessage() . " في الملف: " . $e->getFile() . " السطر: " . $e->getLine());
            return api_exception($e, 500, 'خطأ أثناء حذف الصور');
        }
    }

    /**
     * Serve image with CORS headers
     */
    public function serve($path)
    {
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $fullPath = Storage::disk('public')->path($path);

        return response()->file($fullPath, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
