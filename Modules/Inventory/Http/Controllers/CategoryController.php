<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\StoreCategoryRequest;
use Modules\Inventory\Http\Requests\UpdateCategoryRequest;
use Modules\Inventory\Http\Resources\CategoryResource;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Actions\CreateCategoryAction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * متحكم إدارة الأقسام (CategoryController) - موديول المخازن لإدارة واسترجاع الفئات الرئيسية والفرعية.
 */
class CategoryController extends Controller
{
    protected array $relations = ['parent', 'children', 'creator', 'company', 'image'];

    /**
     * عرض قائمة الأقسام
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $query = Category::with($this->relations);

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                $query->where(function ($q) {
                    $q->whereCompanyIsCurrent()->orWhereNull('company_id');
                });
            }

            if ($request->filled('search')) {
                $query->searchBySynonym($request->search);
            }

            if ($request->filled('parent_id')) {
                $query->where('parent_id', $request->parent_id);
            }

            $perPage = max(1, (int) $request->get('per_page', 20));
            
            $sortBy = $request->input('sort_by', 'name');
            $sortOrder = $request->input('sort_order', 'asc');
            $categories = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

            return api_success(CategoryResource::collection($categories), 'تم استرداد الأقسام بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إضافة قسم جديد
     */
    public function store(StoreCategoryRequest $request, CreateCategoryAction $action): JsonResponse
    {
        try {
            $category = $action->handle($request->validated());
            $category->load($this->relations);
            return api_success(new CategoryResource($category), 'تم معالجة القسم بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض قسم محدد
     */
    public function show(Category $category): JsonResponse
    {
        try {
            $category->load($this->relations);
            return api_success(new CategoryResource($category), 'تم استرداد القسم بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث بيانات قسم
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        try {
            $category->update($request->validated());
            $category->load($this->relations);
            return api_success(new CategoryResource($category), 'تم تحديث القسم بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف قسم
     */
    public function destroy(Category $category): JsonResponse
    {
        try {
            if ($category->products()->exists()) {
                return api_error('لا يمكن حذف القسم لوجود منتجات مرتبطة به.');
            }
            if ($category->children()->exists()) {
                return api_error('لا يمكن حذف القسم لوجود أقسام فرعية مرتبطة به.');
            }
            $category->delete();
            return api_success([], 'تم حذف القسم بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تغيير حالة القسم
     */
    public function toggle(Category $category): JsonResponse
    {
        try {
            $category->update(['active' => !$category->active]);
            return api_success(new CategoryResource($category), 'تم تغيير الحالة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
