<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\StoreAttributeRequest;
use Modules\Inventory\Http\Requests\UpdateAttributeRequest;
use Modules\Inventory\Http\Resources\AttributeResource;
use Modules\Inventory\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * @group إدارة السمات (Module Inventory)
 */
class AttributeController extends Controller
{
    protected array $relations = ['values', 'company', 'creator'];

    /**
     * عرض قائمة السمات
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $query = Attribute::with($this->relations);

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                $query->whereCompanyIsCurrent();
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                      ->orWhereHas('values', fn($vq) => $vq->where('name', 'like', "%$search%"));
                });
            }

            $perPage = max(1, (int) $request->get('per_page', 20));
            $attributes = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return api_success(AttributeResource::collection($attributes), 'تم جلب السمات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إضافة سمة جديدة
     */
    public function store(StoreAttributeRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id;

            DB::beginTransaction();

            $attribute = Attribute::create([
                'name' => $request->name,
                'company_id' => $companyId,
                'created_by' => $authUser->id,
            ]);

            if (!empty($request->values)) {
                foreach ($request->values as $valueData) {
                    $attribute->values()->create([
                        'name' => $valueData['name'],
                        'color' => $valueData['color'] ?? null,
                        'company_id' => $companyId,
                        'created_by' => $authUser->id,
                    ]);
                }
            }

            DB::commit();
            return api_success(new AttributeResource($attribute->load($this->relations)), 'تم إنشاء السمة بنجاح.');
        } catch (Throwable $e) {
            DB::rollBack();
            return api_exception($e);
        }
    }

    /**
     * عرض تفاصيل سمة
     */
    public function show(Attribute $attribute): JsonResponse
    {
        try {
            $attribute->load($this->relations);
            return api_success(new AttributeResource($attribute), 'تم استرداد السمة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث سمة
     */
    public function update(UpdateAttributeRequest $request, Attribute $attribute): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id;

            DB::beginTransaction();

            $attribute->update([
                'name' => $request->name,
                'updated_by' => $authUser->id,
            ]);

            if (isset($request->values)) {
                $requestedValueIds = collect($request->values)->pluck('id')->filter()->all();
                $attribute->values()->whereNotIn('id', $requestedValueIds)->delete();

                foreach ($request->values as $valueData) {
                    $attribute->values()->updateOrCreate(
                        ['id' => $valueData['id'] ?? null],
                        [
                            'name' => $valueData['name'],
                            'color' => $valueData['color'] ?? null,
                            'company_id' => $companyId,
                            'created_by' => $authUser->id,
                        ]
                    );
                }
            }

            DB::commit();
            return api_success(new AttributeResource($attribute->load($this->relations)), 'تم تحديث السمة بنجاح.');
        } catch (Throwable $e) {
            DB::rollBack();
            return api_exception($e);
        }
    }

    /**
     * حذف سمة
     */
    public function destroy(Attribute $attribute): JsonResponse
    {
        try {
            if ($attribute->productVariants()->exists()) {
                return api_error('لا يمكن حذف السمة لارتباطها بمنتجات.', [], 409);
            }

            $attribute->values()->delete();
            $attribute->delete();

            return api_success([], 'تم حذف السمة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تفعيل/تعطيل السمة
     */
    public function toggle(Attribute $attribute): JsonResponse
    {
        try {
            $attribute->update(['active' => !$attribute->active]);
            return api_success(new AttributeResource($attribute), 'تم تغيير حالة السمة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
