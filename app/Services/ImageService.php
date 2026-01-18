<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ImageService
{
    /**
     * ربط مجموعة صور بكيان محدد (منتج، متغير، براند...)
     * تقوم بنقل الملف فيزيائياً من مجلد temp إلى مجلد الكيان
     */
    public static function attachImagesToModel(array $imageIds, Model $model, string $type = 'gallery'): void
    {
        $user = Auth::user();
        if (!$user)
            return;

        $companyId = $user->company_id;
        $modelName = Str::snake(class_basename($model));
        $storageBase = "uploads/{$companyId}/{$modelName}/{$type}";

        foreach ($imageIds as $imageId) {
            $image = Image::where('id', $imageId)
                ->where('created_by', $user->id)
                ->first();

            if (!$image)
                continue;

            // إذا كانت الصورة مربوطة بالفعل وتتبع نفس المسار، لا داعي للنقل
            if (!$image->is_temp && $image->imageable_id == $model->id && $image->imageable_type == get_class($model)) {
                continue;
            }

            // استخراج المسار النسبي من URL المخزن (مثلاً من /storage/uploads/1/temp/x.jpg إلى uploads/1/temp/x.jpg)
            $oldRelativePath = str_replace(Storage::url(''), '', $image->url);
            $oldRelativePath = ltrim($oldRelativePath, '/');

            $ext = pathinfo($image->url, PATHINFO_EXTENSION);
            $fileName = "{$modelName}_{$model->id}_" . uniqid() . '.' . $ext;
            $newRelativePath = "{$storageBase}/{$fileName}";

            // نقل الملف فيزيائياً باستخدام قرص public
            if (Storage::disk('public')->exists($oldRelativePath)) {
                // التأكد من وجود المجلد الوجهة
                Storage::disk('public')->makeDirectory($storageBase);
                Storage::disk('public')->move($oldRelativePath, $newRelativePath);
            }

            $image->update([
                'url' => Storage::url($newRelativePath),
                'imageable_id' => $model->id,
                'imageable_type' => get_class($model),
                'is_temp' => 0,
                'type' => $type,
            ]);
        }
    }

    /**
     * حذف مجموعة صور من التخزين وقاعدة البيانات
     */
    public static function deleteImages(array $imageIds): void
    {
        $images = Image::whereIn('id', $imageIds)->get();

        foreach ($images as $image) {
            // استخراج المسار النسبي للحذف
            $relativePathToDelete = str_replace(Storage::url(''), '', $image->url);
            $relativePathToDelete = ltrim($relativePathToDelete, '/');

            if (Storage::disk('public')->exists($relativePathToDelete)) {
                Storage::disk('public')->delete($relativePathToDelete);
            }

            $image->delete();
        }
    }

    /**
     * فك الربط بين الصور وكيان معين (تصبح الصور يتيمة/مؤقتة مرة أخرى)
     */
    public static function detachImagesFromModel(Model $model): void
    {
        $images = Image::where('imageable_type', get_class($model))
            ->where('imageable_id', $model->id)
            ->get();

        foreach ($images as $image) {
            $image->update([
                'imageable_type' => null,
                'imageable_id' => null,
                'is_temp' => 1,
            ]);
        }
    }

    /**
     * مزامنة الصور: حذف الصور غير الموجودة في القائمة الجديدة، وربط الجديدة
     */
    public static function syncImagesWithModel(array $newImageIds, Model $model, string $type = 'gallery', ?int $primaryImageId = null): void
    {
        $modelClass = get_class($model);

        // جلب الصور المرتبطة حالياً بهذا الموديل
        $currentImageIds = Image::where('imageable_type', $modelClass)
            ->where('imageable_id', $model->id)
            ->pluck('id')
            ->toArray();

        // 1. تحديد الصور التي يجب حذفها (الموجودة حالياً وليست في القائمة الجديدة)
        $toDelete = array_diff($currentImageIds, $newImageIds);
        if (!empty($toDelete)) {
            self::deleteImages($toDelete);
        }

        // 2. ربط ونقل الصور الجديدة
        $toAttach = array_diff($newImageIds, $currentImageIds);
        if (!empty($toAttach)) {
            self::attachImagesToModel($toAttach, $model, $type);
        }

        // 3. التعامل مع الصورة الأساسية (Primary)
        self::handlePrimaryImage($model, $newImageIds, $primaryImageId);
    }

    /**
     * تحديد الصورة الأساسية للموديل
     */
    public static function handlePrimaryImage(Model $model, array $imageIds, ?int $primaryImageId = null): void
    {
        if (empty($imageIds)) {
            return;
        }

        $modelClass = get_class($model);

        // إذا تم تحديد ID معين كصورة أساسية
        if ($primaryImageId && in_array($primaryImageId, $imageIds)) {
            // تصفير كل الصور الأخرى لهذا الموديل
            Image::where('imageable_type', $modelClass)
                ->where('imageable_id', $model->id)
                ->update(['is_primary' => false]);

            // تعيين الصورة المختارة كصورة أساسية
            Image::where('id', $primaryImageId)->update(['is_primary' => true]);
            return;
        }

        // لو مفيش صورة أساسية محددة حالياً لهذا الموديل، نختار أول واحدة في القائمة
        $hasPrimary = Image::where('imageable_type', $modelClass)
            ->where('imageable_id', $model->id)
            ->where('is_primary', true)
            ->exists();

        if (!$hasPrimary) {
            Image::where('id', $imageIds[0])->update(['is_primary' => true]);
        }
    }
}
