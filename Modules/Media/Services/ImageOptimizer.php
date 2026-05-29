<?php

namespace Modules\Media\Services;

// تعليق عربي: خدمة معالجة وتحسين الصور تلقائياً وتحويلها لصيغة WebP لتقليص المساحة وزيادة سرعة النظام.

use Illuminate\Http\UploadedFile;

class ImageOptimizer
{
    /**
     * ضغط الصورة وتحويلها لصيغة WebP.
     *
     * @param UploadedFile $file
     * @param int $quality جودة الصورة الناتجة (0 - 100)
     * @param int|null $maxWidth أقصى عرض مسموح به
     * @return string|false محتوى ملف الصورة المحسن (WebP Binary) أو false في حال الفشل
     */
    public function convertToWebp(UploadedFile $file, int $quality = 80, ?int $maxWidth = 1920): string|false
    {
        $mimeType = $file->getMimeType();
        
        // التحقق من أن الملف صورة مدعومة من مكتبة GD
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
            return false;
        }

        // إنشاء كائن الصورة GD بناءً على نوعها الأصلي
        switch ($mimeType) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($file->getRealPath());
                break;
            case 'image/png':
                $image = @imagecreatefrompng($file->getRealPath());
                // الحفاظ على الشفافية
                if ($image) {
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                }
                break;
            case 'image/gif':
                $image = @imagecreatefromgif($file->getRealPath());
                break;
            case 'image/webp':
                $image = @imagecreatefromwebp($file->getRealPath());
                break;
            default:
                $image = false;
        }

        if (!$image) {
            return false;
        }

        // الحصول على أبعاد الصورة الأصلية
        $width = imagesx($image);
        $height = imagesy($image);

        // تغيير حجم الصورة إذا تجاوزت العرض الأقصى المسموح به مع الحفاظ على النسبة
        if ($maxWidth && $width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = intval(($height / $width) * $maxWidth);
            
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // الحفاظ على الشفافية في الصورة الجديدة
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            
            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resizedImage;
        }

        // حفظ الصورة بصيغة WebP في Stream مؤقت لقراءة محتواها الثنائي
        ob_start();
        $success = imagewebp($image, null, $quality);
        $webpBinary = ob_get_clean();
        
        imagedestroy($image);

        return $success ? $webpBinary : false;
    }
}
