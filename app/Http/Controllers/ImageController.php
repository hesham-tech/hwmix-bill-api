<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage; // إضافة Facade التخزين
use App\Http\Resources\Image\ImageResource;
use App\Services\ImageService;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\Alignment;

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

            // دعم تحويل ملف وسائط من المكتبة العامة إلى سجل صورة مرتبط بالشركة
            if ($request->has('media_file_id') || $request->has('media_file_ids')) {
                $request->validate([
                    'media_file_id' => ['nullable', 'integer', 'exists:media_files,id'],
                    'media_file_ids' => ['nullable', 'array'],
                    'media_file_ids.*' => ['integer', 'exists:media_files,id'],
                    'type' => ['nullable', 'string'],
                ]);

                $user = Auth::user();
                $companyId = $user->active_company_id;
                $type = $request->input('type', 'misc');
                $createdImages = [];

                $ids = $request->has('media_file_ids')
                    ? $request->input('media_file_ids')
                    : [$request->input('media_file_id')];

                $manager = new ImageManager(new Driver());

                foreach ($ids as $id) {
                    if (empty($id)) continue;
                    $mediaFile = \Modules\Media\Models\MediaFile::findOrFail($id);

                    $companyModel = \App\Models\Company::find($companyId);
                    $watermarkCompany = $companyModel && $companyModel->canCustomizeWatermark() ? $companyModel : \App\Models\Company::find(1);
                    $applyTo = $watermarkCompany ? ($watermarkCompany->watermark_settings['apply_to'] ?? ['product', 'variant']) : ['product', 'variant'];

                    if (in_array($type, $applyTo)) {
                        $sourcePath = storage_path('app/public/' . $mediaFile->file_path);
                        if (file_exists($sourcePath)) {
                            $fileName = "temp_{$user->id}_" . uniqid() . '.webp';
                            $dir = "uploads/{$companyId}/temp";
                            Storage::disk('public')->makeDirectory($dir);
                            $path = "{$dir}/{$fileName}";

                            $img = $manager->decode($sourcePath);
                            $img->scaleDown(800, 800);
                            $canvas = $manager->createImage(800, 800)->fill('ffffff');
                            $canvas->insert($img, 0, 0, 'center');
                            $this->applyWatermark($canvas, \App\Models\Company::find($companyId));
                            
                            $encoded = $canvas->encodeUsingFormat(Format::WEBP, 90);
                            Storage::disk('public')->put($path, (string) $encoded);
                            
                            $finalUrl = $path;
                        } else {
                            $finalUrl = '/storage/' . $mediaFile->file_path;
                        }
                    } else {
                        $finalUrl = '/storage/' . $mediaFile->file_path;
                    }

                    $image = Image::create([
                        'url' => $finalUrl,
                        'type' => $type,
                        'company_id' => $companyId,
                        'created_by' => $user->id,
                        'is_temp' => 1,
                    ]);

                    $createdImages[] = $image;
                }

                return api_success(
                    $request->has('media_file_ids')
                        ? ImageResource::collection($createdImages)
                        : new ImageResource($createdImages[0]),
                    'تم ربط ملفات الوسائط بنجاح'
                );
            }

            // سجل بيانات ملفات الصور
            logger($request->file('images'));

            $request->validate([
                'images' => ['required', 'array'],
                'images.*' => ['image', 'max:5120'], // 5 ميجابايت كحد أقصى
                'type' => ['nullable', 'string'],
            ]);

            $user = Auth::user();
            $companyId = $user->active_company_id;
            $type = $request->input('type', 'misc');
            $uploadedImages = [];

            // تهيئة مكتبة معالجة الصور
            $manager = new ImageManager(new Driver());

            foreach ($request->file('images') as $file) {
                // اسم الملف الفريد بصيغة WebP لتوفير المساحة والسرعة
                $fileName = "temp_{$user->id}_" . uniqid() . '.webp';
                
                $dir = "uploads/{$companyId}/temp";
                Storage::disk('public')->makeDirectory($dir);
                $path = "{$dir}/{$fileName}";

                $companyModel = \App\Models\Company::find($companyId);
                $watermarkCompany = $companyModel && $companyModel->canCustomizeWatermark() ? $companyModel : \App\Models\Company::find(1);
                $applyTo = $watermarkCompany ? ($watermarkCompany->watermark_settings['apply_to'] ?? ['product', 'variant']) : ['product', 'variant'];

                if (in_array($type, $applyTo)) {
                    // 1. معالجة وتوحيد صور المنتجات
                    $img = $manager->decode($file->getRealPath());
                    
                    // تصغير الصورة بحيث لا تتجاوز 800x800 مع الحفاظ على نسبة الأبعاد الأصلية
                    $img->scaleDown(800, 800);
                    
                    // إنشاء لوحة بيضاء بحجم 800x800
                    $canvas = $manager->createImage(800, 800)->fill('ffffff');
                    
                    // وضع الصورة في منتصف اللوحة
                    $canvas->insert($img, 0, 0, 'center');
                    
                    $this->applyWatermark($canvas, \App\Models\Company::find($companyId));
                    
                    // تحويل إلى WebP بجودة 90%
                    $encoded = $canvas->encodeUsingFormat(Format::WEBP, 90);
                    
                    // الحفظ في التخزين
                    Storage::disk('public')->put($path, (string) $encoded);
                } else {
                    // الصور العادية (بدون معالجة قوية، مجرد تحويل إلى WebP مثلا)
                    $img = $manager->decode($file->getRealPath());
                    $encoded = $img->encodeUsingFormat(Format::WEBP, 90);
                    Storage::disk('public')->put($path, (string) $encoded);
                }

                $image = Image::create([
                    'url' => $path,
                    'type' => $type,
                    'company_id' => $companyId,
                    'created_by' => $user->id,
                    'is_temp' => 1,
                ]);

                $uploadedImages[] = $image;
            }

            return api_success(ImageResource::collection($uploadedImages), 'تم رفع ومعالجة الصور بنجاح');
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

    /**
     * Apply watermark settings to an image canvas
     */
    protected function applyWatermark($canvas, \App\Models\Company $company)
    {
        $companyModel = \App\Models\Company::find($company->id);
        $watermarkCompany = $companyModel && $companyModel->canCustomizeWatermark() ? $companyModel : \App\Models\Company::find(1);
        
        $settings = $watermarkCompany ? $watermarkCompany->watermark_settings : [
            'enabled' => true,
            'type' => 'text',
            'text' => 'hwnix.com',
            'position' => 'bottom-right',
            'opacity' => 40,
            'size' => 24,
            'color' => '#888888',
            'stroke' => true,
        ];

        if (!($settings['enabled'] ?? true)) {
            return;
        }

        $type = $settings['type'] ?? 'text';
        $padding = 20;

        $x = 0; $y = 0;
        $alignHorizontal = 'center'; $alignVertical = 'middle';

        switch ($settings['position'] ?? 'bottom-right') {
            case 'top-left':
                $x = $padding; $y = $padding;
                $alignHorizontal = 'left'; $alignVertical = 'top';
                break;
            case 'top-right':
                $x = 800 - $padding; $y = $padding;
                $alignHorizontal = 'right'; $alignVertical = 'top';
                break;
            case 'bottom-left':
                $x = $padding; $y = 800 - $padding;
                $alignHorizontal = 'left'; $alignVertical = 'bottom';
                break;
            case 'bottom-right':
                $x = 800 - $padding; $y = 800 - $padding;
                $alignHorizontal = 'right'; $alignVertical = 'bottom';
                break;
            case 'center':
                $x = 400; $y = 400;
                break;
        }

        if (in_array($type, ['image', 'both']) && !empty($settings['image_path'])) {
            $logoPath = storage_path('app/public/' . $settings['image_path']);
            if (file_exists($logoPath)) {
                try {
                    $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                    $logo = $manager->decode($logoPath);
                    $scalePercent = (int) ($settings['scale'] ?? 20);
                    $targetWidth = (int) (800 * ($scalePercent / 100));
                    $logo->scaleDown(width: $targetWidth);
                    
                    $canvas->insert($logo, 0, 0, $settings['position'] ?? 'bottom-right');
                    
                    if ($type == 'both') {
                        if (str_contains($settings['position'], 'bottom')) {
                            $y -= ($logo->height() + 10);
                        } else {
                            $y += ($logo->height() + 10);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error("Watermark Image Error: " . $e->getMessage());
                }
            }
        }

        if (in_array($type, ['text', 'both']) && !empty($settings['text'])) {
            $canvas->text($settings['text'], $x, $y, function($font) use ($settings, $alignHorizontal, $alignVertical) {
                $font->file(base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'));
                $font->color($settings['color'] ?? '#888888');
                $font->size((int) ($settings['size'] ?? 24));
                if ($settings['stroke'] ?? true) {
                    $font->stroke('#ffffff', 3);
                }
                $font->align($alignHorizontal, $alignVertical);
            });
        }
    }
}
