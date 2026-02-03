<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentMethod\StorePaymentMethodRequest;
use App\Http\Requests\PaymentMethod\UpdatePaymentMethodRequest;
use App\Http\Resources\PaymentMethod\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\ImageService;
use Throwable;

class PaymentMethodController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'company',
            'creator',
            'payments',
            'image',
        ];
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * عرض طرق الدفع المتاحة
     * 
     * استرجاع كافة الوسائل المالية المقبولة في النظام (كاش، تحويل بنكي، محفظة إلكترونية).
     * 
     * @queryParam active boolean فلترة حسب النشط فقط.
     * 
     * @apiResourceCollection App\Http\Resources\PaymentMethod\PaymentMethodResource
     * @apiResourceModel App\Models\PaymentMethod
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            $query = PaymentMethod::query()->with($this->relations);

            // تطبيق منطق الصلاحيات والسكوبس
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى الجميع
            } elseif ($authUser->hasAnyPermission([perm_key('payment_methods.view_all'), perm_key('admin.company')])) {
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.view_children'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.view_self'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض طرق الدفع.');
            }

            // فلاتر البحث
            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->input('search') . '%');
            }

            if ($request->has('is_system')) {
                $query->where('is_system', (bool) $request->get('is_system'));
            }

            if ($request->filled('active')) {
                $query->where('active', (bool) $request->get('active'));
            }

            // الفرز والتصفح
            $perPage = (int) $request->input('per_page', 12);
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');

            $query->orderBy($sortField, $sortOrder);

            $paymentMethods = ($perPage == -1) ? $query->get() : $query->paginate(max(1, $perPage));

            $resource = PaymentMethodResource::collection($paymentMethods);

            return api_success(
                ($perPage == -1) ? $resource : $resource->response()->getData(true),
                'تم جلب طرق الدفع بنجاح.'
            );
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * إضافة طريقة دفع
     * 
     * @bodyParam name string required اسم الطريقة. Example: فودافون كاش
     * @bodyParam code string required كود فريد للطريقة. Example: VFC
     * @bodyParam active boolean الحالة. Example: true
     */
    public function store(StorePaymentMethodRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;

                $isSuperAdmin = $authUser->hasPermissionTo(perm_key('admin.super'));

                // تحديد الشركة
                $validatedData['company_id'] = ($isSuperAdmin && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $companyId;

                // إذا كان سوبر أدمن، نجعلها طريقة سيستم افتراضياً إذا لم تكن لشركة محددة
                if ($isSuperAdmin && !isset($validatedData['company_id'])) {
                    $validatedData['is_system'] = true;
                }

                $paymentMethod = PaymentMethod::create($validatedData);

                // ربط الصورة
                if (!empty($validatedData['image_id'])) {
                    if ($isSuperAdmin && $paymentMethod->is_system) {
                        // منطق السوبر أدمن: حفظ في مجلد seeders باسم الكود
                        $tempImage = \App\Models\Image::find($validatedData['image_id']);
                        if ($tempImage) {
                            $oldPath = str_replace(\Illuminate\Support\Facades\Storage::url(''), '', $tempImage->url);
                            $oldPath = ltrim($oldPath, '/');
                            $ext = pathinfo($tempImage->url, PATHINFO_EXTENSION);
                            $newPath = "seeders/payment-methods/{$paymentMethod->code}.{$ext}";

                            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($oldPath)) {
                                \Illuminate\Support\Facades\Storage::disk('public')->move($oldPath, $newPath);
                            }

                            $tempImage->update([
                                'url' => \Illuminate\Support\Facades\Storage::url($newPath),
                                'imageable_id' => $paymentMethod->id,
                                'imageable_type' => PaymentMethod::class,
                                'is_temp' => 0,
                                'type' => 'logo',
                                'company_id' => null,
                            ]);
                        }
                    } else {
                        // منطق الشركة العادي
                        ImageService::attachImagesToModel([$validatedData['image_id']], $paymentMethod, 'logo');
                    }
                }

                $paymentMethod->load($this->relations);
                DB::commit();
                return api_success(new PaymentMethodResource($paymentMethod), 'تم إنشاء طريقة الدفع بنجاح.', 201);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ طريقة الدفع.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * عرض تفاصيل طريقة دفع
     * 
     * @urlParam id required معرف الطريقة. Example: 1
     */
    public function show(string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $paymentMethod = PaymentMethod::with($this->relations)->findOrFail($id);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('payment_methods.view_all'), perm_key('admin.company')])) {
                $canView = $paymentMethod->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.view_children'))) {
                $canView = $paymentMethod->belongsToCurrentCompany() && $paymentMethod->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.view_self'))) {
                $canView = $paymentMethod->belongsToCurrentCompany() && $paymentMethod->createdByCurrentUser();
            }

            if (!$canView) {
                return api_forbidden('ليس لديك إذن لعرض طريقة الدفع هذه.');
            }

            return api_success(new PaymentMethodResource($paymentMethod), 'تم استرداد طريقة الدفع بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * تحديث طريقة دفع
     * 
     * @urlParam id required معرف الطريقة. Example: 1
     * @bodyParam name string اسم الطريقة. Example: تحويل بنكي
     */
    public function update(UpdatePaymentMethodRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            $paymentMethod = PaymentMethod::findOrFail($id);

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('payment_methods.update_all'), perm_key('admin.company')])) {
                $canUpdate = $paymentMethod->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.update_children'))) {
                $canUpdate = $paymentMethod->belongsToCurrentCompany() && $paymentMethod->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.update_self'))) {
                $canUpdate = $paymentMethod->belongsToCurrentCompany() && $paymentMethod->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث طريقة الدفع هذه.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;
                $isSuperAdmin = $authUser->hasPermissionTo(perm_key('admin.super'));

                // حماية تغيير الشركة لغير السوبر أدمن
                if (isset($validatedData['company_id']) && !$isSuperAdmin) {
                    unset($validatedData['company_id']);
                }

                $paymentMethod->update($validatedData);

                // تحديث الصورة
                if (isset($validatedData['image_id'])) {
                    if ($isSuperAdmin && $paymentMethod->is_system) {
                        // منطق السوبر أدمن: استبدال الصورة في مجلد seeders
                        $oldImage = $paymentMethod->image;
                        if ($oldImage) {
                            $oldPhysicalPath = str_replace(\Illuminate\Support\Facades\Storage::url(''), '', $oldImage->url);
                            $oldPhysicalPath = ltrim($oldPhysicalPath, '/');
                            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($oldPhysicalPath)) {
                                \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPhysicalPath);
                            }
                            $oldImage->delete();
                        }

                        $tempImage = \App\Models\Image::find($validatedData['image_id']);
                        if ($tempImage) {
                            $tempPath = str_replace(\Illuminate\Support\Facades\Storage::url(''), '', $tempImage->url);
                            $tempPath = ltrim($tempPath, '/');
                            $ext = pathinfo($tempImage->url, PATHINFO_EXTENSION);
                            $newPath = "seeders/payment-methods/{$paymentMethod->code}.{$ext}";

                            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($tempPath)) {
                                \Illuminate\Support\Facades\Storage::disk('public')->move($tempPath, $newPath);
                            }

                            $tempImage->update([
                                'url' => \Illuminate\Support\Facades\Storage::url($newPath),
                                'imageable_id' => $paymentMethod->id,
                                'imageable_type' => PaymentMethod::class,
                                'is_temp' => 0,
                                'type' => 'logo',
                                'company_id' => null,
                            ]);
                        }
                    } else {
                        // منطق الشركة العادي
                        $oldImage = $paymentMethod->image;
                        if ($oldImage && str_contains($oldImage->url, 'seeders/')) {
                            // إذا كانت الصورة الحالية من السـيدر، نحذف السجل فقط ولا نحذف الملف فيزيائياً
                            $oldImage->delete();
                            // ثم نربط الجديدة
                            ImageService::attachImagesToModel([$validatedData['image_id']], $paymentMethod, 'logo');
                        } else {
                            // المنطق الطبيعي للمزامنة (سيبوم بحذف الملف القديم إذا لم يكن سـيدر)
                            $newImageIds = $validatedData['image_id'] ? [$validatedData['image_id']] : [];
                            ImageService::syncImagesWithModel($newImageIds, $paymentMethod, 'logo');
                        }
                    }
                }

                $paymentMethod->load($this->relations);
                DB::commit();
                return api_success(new PaymentMethodResource($paymentMethod), 'تم تحديث طريقة الدفع بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث طريقة الدفع.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * حذف طريقة دفع
     * 
     * @urlParam id required معرف الطريقة. Example: 1
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $paymentMethod = PaymentMethod::with(['company', 'creator', 'payments'])->findOrFail($id);

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('payment_methods.delete_all'), perm_key('admin.company')])) {
                $canDelete = $paymentMethod->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.delete_children'))) {
                $canDelete = $paymentMethod->belongsToCurrentCompany() && $paymentMethod->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.delete_self'))) {
                $canDelete = $paymentMethod->belongsToCurrentCompany() && $paymentMethod->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف طريقة الدفع هذه.');
            }

            // ✅ حماية من حذف طرق الدفع الأساسية (إلا للسوبر أدمن)
            if ($paymentMethod->is_system && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_error(
                    'لا يمكن حذف طريقة دفع أساسية من النظام. يمكنك تعطيلها بدلاً من ذلك.',
                    [
                        'suggestion' => 'يمكنك تعطيل الطريقة بتغيير حالة active إلى false',
                        'is_system' => true
                    ],
                    403
                );
            }

            // التحقق من وجود ارتباطات بدفعات
            if ($paymentMethod->payments()->exists()) {
                return api_error('لا يمكن حذف طريقة الدفع لأنها مستخدمة في دفعات موجودة.', [], 422);
            }

            DB::beginTransaction();
            try {
                $isSuperAdmin = $authUser->hasPermissionTo(perm_key('admin.super'));
                $deletedPaymentMethod = $paymentMethod->replicate();
                $deletedPaymentMethod->setRelations($paymentMethod->getRelations());

                if ($paymentMethod->image) {
                    if ($isSuperAdmin && $paymentMethod->is_system) {
                        // حذف السجل والملف الفيزيائي للسوبر أدمن
                        $physicalPath = str_replace(\Illuminate\Support\Facades\Storage::url(''), '', $paymentMethod->image->url);
                        $physicalPath = ltrim($physicalPath, '/');
                        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($physicalPath)) {
                            \Illuminate\Support\Facades\Storage::disk('public')->delete($physicalPath);
                        }
                        $paymentMethod->image->delete();
                    } else {
                        // للمستخدم العادي: حذف السجل فقط إذا كان سـيدر، وإلا حذف طبيعي
                        if (str_contains($paymentMethod->image->url, 'seeders/')) {
                            $paymentMethod->image->delete();
                        } else {
                            ImageService::deleteImages([$paymentMethod->image->id]);
                        }
                    }
                }

                $paymentMethod->delete();
                DB::commit();
                return api_success($deletedPaymentMethod, 'تم حذف طريقة الدفع بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * تفعيل/تعطيل طريقة دفع
     * 
     * @urlParam id required معرف الطريقة. Example: 1
     */
    public function toggle(string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $paymentMethod = PaymentMethod::findOrFail($id);

            // التحقق من صلاحيات التحديث (التبديل)
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('payment_methods.update_all'), perm_key('admin.company')])) {
                $canUpdate = $paymentMethod->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.update_children'))) {
                $canUpdate = $paymentMethod->belongsToCurrentCompany() && $paymentMethod->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.update_self'))) {
                $canUpdate = $paymentMethod->belongsToCurrentCompany() && $paymentMethod->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتعديل حالة طريقة الدفع هذه.');
            }

            // تبديل الحالة
            $paymentMethod->active = !$paymentMethod->active;
            $paymentMethod->save();

            $status = $paymentMethod->active ? 'مفعّلة' : 'معطّلة';
            return api_success(
                $paymentMethod,
                "طريقة الدفع '{$paymentMethod->name}' الآن {$status}."
            );
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
