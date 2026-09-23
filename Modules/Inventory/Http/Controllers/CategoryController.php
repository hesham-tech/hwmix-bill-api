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
                    $q->whereCompanyIsCurrent()->orWhere('is_system', true);
                });
            }

            if ($request->filled('search')) {
                $query->searchBySynonym($request->search);
            }

            if ($request->filled('parent_id')) {
                if ($request->parent_id === 'null') {
                    $query->whereNull('parent_id');
                } else {
                    $query->where('parent_id', $request->parent_id);
                }
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
             return api_success(new CategoryResource($category), 'تم معالجة القسم بنجاح.', 201);
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
             $authUser = Auth::user();
             if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$category->is_system && $category->company_id !== $authUser->active_company_id) {
                 return api_forbidden('ليس لديك صلاحية للوصول إلى هذا القسم.');
             }
             $category->load($this->relations);
             return api_success(new CategoryResource($category), 'تم استرداد القسم بنجاح.');
         } catch (Throwable $e) {
             return api_exception($e);
         }
     }

     /**
      * عرض مسار القسم (Breadcrumbs)
      */
     public function breadcrumbs(Category $category): JsonResponse
     {
         try {
             $breadcrumbs = [];
             $current = $category;
             while ($current) {
                 $breadcrumbs[] = [
                     'id' => $current->id,
                     'name' => $current->name,
                 ];
                 $current = $current->parent;
             }
             
             return api_success(array_reverse($breadcrumbs), 'تم استرداد مسار القسم بنجاح.');
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
             $authUser = Auth::user();
             if (!$authUser->hasPermissionTo(perm_key('admin.super')) && $category->company_id !== $authUser->active_company_id) {
                 return api_forbidden('ليس لديك صلاحية للوصول إلى هذا القسم.');
             }
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
             $authUser = Auth::user();
             if (!$authUser->hasPermissionTo(perm_key('admin.super')) && $category->company_id !== $authUser->active_company_id) {
                 return api_forbidden('ليس لديك صلاحية للوصول إلى هذا القسم.');
             }
             if ($category->products()->exists()) {
                 return api_error('لا يمكن حذف القسم لوجود منتجات مرتبطة به.', [], 409);
             }
             if ($category->children()->exists()) {
                 return api_error('لا يمكن حذف القسم لوجود أقسام فرعية مرتبطة به.', [], 409);
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

    /**
     * تحويل القسم لسجل عالمي (Global)
     */
    public function globalize(Category $category): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('ليس لديك صلاحية للقيام بهذه العملية.');
            }
            $category->update(['is_system' => true]);
            return api_success(new CategoryResource($category), 'تم تحويل القسم لسجل عالمي بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تخصيص القسم للشركة الحالية
     */
    public function localize(Category $category): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('ليس لديك صلاحية للقيام بهذه العملية.');
            }
            $category->update(['is_system' => false]);
            return api_success(new CategoryResource($category), 'تم تخصيص القسم للشركة الحالية بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * دمج فئتين
     */
    public function merge(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('ليس لديك صلاحية للقيام بهذه العملية.');
            }

            $request->validate([
                'source_id' => 'required|exists:categories,id',
                'target_id' => 'required|exists:categories,id|different:source_id',
            ]);

            $source = Category::findOrFail($request->source_id);
            $target = Category::findOrFail($request->target_id);

            DB::transaction(function () use ($source, $target) {
                // نقل المنتجات المرتبطة
                $source->products()->update(['category_id' => $target->id]);
                // نقل الأقسام الفرعية المرتبطة
                $source->children()->update(['parent_id' => $target->id]);
                
                $source->delete();
            });

            return api_success([], 'تم دمج القسمين بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
