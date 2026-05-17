<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\StoreBrandRequest;
use Modules\Inventory\Http\Requests\UpdateBrandRequest;
use Modules\Inventory\Http\Resources\BrandResource;
use Modules\Inventory\Models\Brand;
use Modules\Inventory\Actions\CreateBrandAction;
use Modules\Inventory\Actions\UpdateBrandAction;
use Modules\Inventory\Actions\DeleteBrandAction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * @group إدارة العلامات التجارية (Module Inventory)
 */
class BrandController extends Controller
{
    protected array $relations = ['creator', 'company', 'products', 'image'];

    /**
     * عرض قائمة الماركات
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $query = Brand::with($this->relations);

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                $query->where(function ($q) {
                    $q->whereCompanyIsCurrent()->orWhereNull('company_id');
                });
            }

            if ($request->filled('search')) {
                $query->searchBySynonym($request->search);
            }

            $perPage = max(1, (int) $request->get('per_page', 12));
            $brands = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return api_success(BrandResource::collection($brands), 'تم استرداد العلامات التجارية بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إضافة ماركة جديدة
     */
    public function store(StoreBrandRequest $request, CreateBrandAction $action): JsonResponse
    {
        try {
            $brand = $action->handle($request->validated());
            $brand->load($this->relations);
            return api_success(new BrandResource($brand), 'تم معالجة العلامة التجارية بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض ماركة محددة
     */
    public function show(Brand $brand): JsonResponse
    {
        try {
            $brand->load($this->relations);
            return api_success(new BrandResource($brand), 'تم استرداد العلامة التجارية بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث بيانات ماركة
     */
    public function update(UpdateBrandRequest $request, Brand $brand, UpdateBrandAction $action): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['brand'] = $brand;
            $updatedBrand = $action->handle($data);
            $updatedBrand->load($this->relations);
            return api_success(new BrandResource($updatedBrand), 'تم تحديث العلامة التجارية بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف ماركة
     */
    public function destroy(Brand $brand, DeleteBrandAction $action): JsonResponse
    {
        try {
            $action->handle(['brand' => $brand]);
            return api_success([], 'تم حذف العلامة التجارية بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تغيير حالة الماركة (تفعيل/تعطيل)
     */
    public function toggle(Brand $brand): JsonResponse
    {
        try {
            $brand->update(['active' => !$brand->active]);
            return api_success(new BrandResource($brand), 'تم تغيير حالة العلامة التجارية بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
