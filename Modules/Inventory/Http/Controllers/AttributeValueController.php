<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\StoreAttributeValueRequest;
use Modules\Inventory\Http\Requests\UpdateAttributeValueRequest;
use Modules\Inventory\Http\Resources\AttributeValueResource;
use Modules\Inventory\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * @group إدارة قيم السمات (Module Inventory)
 */
class AttributeValueController extends Controller
{
    protected array $relations = ['attribute', 'creator'];

    /**
     * عرض قائمة قيم السمات
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $query = AttributeValue::with($this->relations);

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                $query->whereCompanyIsCurrent();
            }

            if ($request->filled('attribute_id')) {
                $query->where('attribute_id', $request->attribute_id);
            }

            $perPage = max(1, (int) $request->get('per_page', 20));
            $values = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return api_success(AttributeValueResource::collection($values), 'تم جلب قيم السمات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إضافة قيمة سمة جديدة
     */
    public function store(StoreAttributeValueRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            $value = AttributeValue::create(array_merge($request->validated(), [
                'company_id' => $companyId,
                'created_by' => $authUser->id,
            ]));

            return api_success(new AttributeValueResource($value->load($this->relations)), 'تم إنشاء قيمة السمة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض تفاصيل قيمة سمة
     */
    public function show(AttributeValue $attributeValue): JsonResponse
    {
        try {
            $attributeValue->load($this->relations);
            return api_success(new AttributeValueResource($attributeValue), 'تم استرداد قيمة السمة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث قيمة سمة
     */
    public function update(UpdateAttributeValueRequest $request, AttributeValue $attributeValue): JsonResponse
    {
        try {
            $attributeValue->update($request->validated());
            $attributeValue->load($this->relations);
            return api_success(new AttributeValueResource($attributeValue), 'تم تحديث قيمة السمة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف قيمة سمة
     */
    public function destroy(AttributeValue $attributeValue): JsonResponse
    {
        try {
            $attributeValue->delete();
            return api_success([], 'تم حذف قيمة السمة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
