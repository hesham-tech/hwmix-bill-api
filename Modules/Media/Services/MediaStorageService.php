<?php

namespace Modules\Media\Services;

//   خدمة لإدارة وتخزين وحذف ملفات الوسائط والصور محلياً مع ربطها بالشركة والمستخدم الموثق.

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Modules\Media\Models\MediaFile;

class MediaStorageService
{
    protected ImageOptimizer $optimizer;

    public function __construct(ImageOptimizer $optimizer)
    {
        $this->optimizer = $optimizer;
    }

    /**
     * رفع ملف ومعالجته وحفظه في التخزين وجدول الوسائط.
     *
     * @param UploadedFile $file
     * @param int $companyId
     * @param int|null $userId
     * @return MediaFile
     */
    public function store(UploadedFile $file, int $companyId, ?int $userId = null, bool $private = false): MediaFile
    {
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        $isImage = str_starts_with($mimeType, 'image/');
        $isSvg = ($mimeType === 'image/svg+xml' || $extension === 'svg');

        $diskName = $private ? 'local' : 'public';
        $companyFolder = ($private ? 'label-template-assets/' : 'media/') . $companyId;
        Storage::disk($diskName)->makeDirectory($companyFolder);

        if ($isImage && !$isSvg) {
            // تحسين وضغط الصورة بصيغة WebP
            $optimizedData = $this->optimizer->convertToWebp($file);

            if ($optimizedData !== false) {
                $filename = uniqid() . '.webp';
                $path = $companyFolder . '/' . $filename;

                Storage::disk($diskName)->put($path, $optimizedData);
                $fileSize = Storage::disk($diskName)->size($path);
                $mimeType = 'image/webp';
            } else {
                // في حال فشل تحويل GD، نرفعها كملف عادي احتياطياً
                $path = $file->store($companyFolder, $diskName);
                $filename = basename($path);
                $fileSize = $file->getSize();
            }
        } else {
            // رفع الملفات العادية (PDF/SVG/etc.) مباشرة
            $path = $file->store($companyFolder, $diskName);
            $filename = basename($path);
            $fileSize = $file->getSize();
        }

        // تسجيل السجل في قاعدة البيانات
        return MediaFile::create([
            'filename' => $filename,
            'original_name' => $originalName,
            'file_path' => $path,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'company_id' => $companyId,
            'created_by' => $userId ?? Auth::id(),
        ]);
    }

    /**
     * حذف ملف وسائط من التخزين والـ DB.
     *
     * @param MediaFile $mediaFile
     * @return bool
     */
    public function delete(MediaFile $mediaFile): bool
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('label_template_asset_references') &&
            \Illuminate\Support\Facades\DB::table('label_template_asset_references')->where('media_file_id', $mediaFile->id)->exists()) {
            abort(409, 'هذه الصورة مرتبطة بنسخة محفوظة من قالب ملصقات ولا يمكن حذفها.');
        }
        $diskName = str_starts_with($mediaFile->file_path, 'label-template-assets/') ? 'local' : 'public';
        try {
            // حذف الملف الفعلي من وحدة التخزين
            if (Storage::disk($diskName)->exists($mediaFile->file_path)) {
                Storage::disk($diskName)->delete($mediaFile->file_path);
            }

            // حذف السجل من قاعدة البيانات (Soft Delete)
            return $mediaFile->delete();
        } catch (\Exception $e) {
            return false;
        }
    }
}
