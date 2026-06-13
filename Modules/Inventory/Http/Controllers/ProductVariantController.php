<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductVariant\StoreProductVariantRequest;
use App\Http\Requests\ProductVariant\UpdateProductVariantRequest;
use Modules\Inventory\Http\Resources\ProductVariantResource;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductVariant;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\Attribute;
use Modules\Inventory\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * متحكم متغيرات المنتجات - إدارة العمليات على متغيرات المنتجات ومخزونها في موديول المخزون
 */
class ProductVariantController extends Controller
{
    protected array $relations = [
        'creator',
        'company',
        'product',
        'product.creator',
        'product.company',
        'product.category',
        'product.brand',
        'product.image',
        'image',
        'attributes.attributeValue',
        'stocks.warehouse',
    ];

    /**
     * @group إدارة المتغيرات (Product Variants)
     * 
     * عرض قائمة أصناف المنتجات
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            $query = ProductVariant::with($this->relations);
            $companyId = $authUser->active_company_id ?? null;

            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع المتغيرات
            } elseif ($authUser->hasAnyPermission([perm_key('product_variants.view_all'), perm_key('admin.company')])) {
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.view_children'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.view_self'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك صلاحية لعرض متغيرات المنتجات.');
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q
                        ->where('sku', 'like', "%$search%")
                        ->orWhere('barcode', 'like', "%$search%")
                        ->orWhereHas('product', function ($pq) use ($search) {
                            $pq->where('name', 'like', "%$search%")
                                ->orWhere('desc', 'like', "%$search%");
                        });
                });
            }
            if ($request->filled('product_id')) {
                $query->where('product_id', $request->input('product_id'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->boolean('has_stock')) {
                $query->whereHas('stocks', function ($q) {
                    $q->where('quantity', '>', 0);
                });
            }

            $perPage = max(1, (int) $request->get('per_page', 20));
            $sortField = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $productVariants = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            if ($productVariants->isEmpty()) {
                return api_success([], 'لم يتم العثور على متغيرات منتجات.');
            } else {
                return api_success(ProductVariantResource::collection($productVariants), 'تم جلب متغيرات المنتجات بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group إدارة المتغيرات (Product Variants)
     * 
     * إضافة متغير لمنتج
     */
    public function store(StoreProductVariantRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id ?? null;

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }
            if (!$companyId) {
                return api_forbidden('يتطلب الارتباط بالشركة.');
            }

            // صلاحيات إنشاء متغير منتج
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('product_variants.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك صلاحية لإنشاء متغيرات المنتجات.');
            }

            DB::beginTransaction();
            try {
                $validated = $request->validated();

                // التحقق من أن المنتج الأم تابع لشركة المستخدم أو أن المستخدم super_admin
                $product = Product::with('company')->find($validated['product_id']);

                // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للمنتج الأم.
                $variantCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validated['company_id']))
                    ? $validated['company_id']
                    : $product->company_id; // استخدم company_id للمنتج الأم

                // التأكد من أن المستخدم مصرح له بإنشاء متغير لهذه الشركة
                if ($variantCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء متغيرات منتجات لشركتك الحالية ما لم تكن مسؤولاً عامًا.');
                }

                $validated['company_id'] = $variantCompanyId;
                $validated['created_by'] = $authUser->id; // من أنشأ المتغير

                $productVariant = ProductVariant::create($validated);

                // حفظ الخصائص (attributes)
                if ($request->has('attributes') && is_array($request->input('attributes'))) {
                    foreach ($request->input('attributes') as $attr) {
                        if (!empty($attr['attribute_id']) && !empty($attr['attribute_value_id'])) {
                            $productVariant->attributes()->create([
                                'attribute_id' => $attr['attribute_id'],
                                'attribute_value_id' => $attr['attribute_value_id'],
                                'company_id' => $variantCompanyId,
                                'created_by' => $authUser->id,
                            ]);
                        }
                    }
                }

                // حفظ المخزون (stocks)
                if ($request->has('stocks') && is_array($request->input('stocks'))) {
                    foreach ($request->input('stocks') as $stockData) {
                        if (!empty($stockData['warehouse_id'])) {
                            Stock::create([
                                'variant_id' => $productVariant->id,
                                'warehouse_id' => $stockData['warehouse_id'],
                                'quantity' => $stockData['quantity'] ?? 0,
                                'reserved' => $stockData['reserved'] ?? 0,
                                'min_quantity' => $stockData['min_quantity'] ?? 0,
                                'cost' => $stockData['cost'] ?? null,
                                'batch' => $stockData['batch'] ?? null,
                                'expiry' => $stockData['expiry'] ?? null,
                                'loc' => $stockData['loc'] ?? null,
                                'status' => $stockData['status'] ?? 'available',
                                'company_id' => $variantCompanyId,
                                'created_by' => $authUser->id,
                            ]);
                        }
                    }
                }

                DB::commit();
                return api_success(new ProductVariantResource($productVariant->load($this->relations)), 'تم إنشاء متغير المنتج بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين متغير المنتج.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ متغير المنتج.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group إدارة المتغيرات (Product Variants)
     * 
     * عرض متغير محدد
     */
    public function show(string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id ?? null;

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }
            if (!$companyId) {
                return api_forbidden('يتطلب الارتباط بالشركة.');
            }

            $productVariant = ProductVariant::with($this->relations)->findOrFail($id);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('product_variants.view_all'), perm_key('admin.company')])) {
                $canView = $productVariant->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.view_children'))) {
                $canView = $productVariant->belongsToCurrentCompany() && $productVariant->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.view_self'))) {
                $canView = $productVariant->belongsToCurrentCompany() && $productVariant->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new ProductVariantResource($productVariant), 'تم استرداد متغير المنتج بنجاح.');
            }

            return api_forbidden('ليس لديك صلاحية لعرض متغير المنتج هذا.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group إدارة المتغيرات (Product Variants)
     * 
     * تحديث متغير
     */
    public function update(UpdateProductVariantRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id ?? null;

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }
            if (!$companyId) {
                return api_forbidden('يتطلب الارتباط بالشركة.');
            }

            $productVariant = ProductVariant::with(['company', 'creator', 'product'])->findOrFail($id);

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('product_variants.update_all'), perm_key('admin.company')])) {
                $canUpdate = $productVariant->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.update_children'))) {
                $canUpdate = $productVariant->belongsToCurrentCompany() && $productVariant->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.update_self'))) {
                $canUpdate = $productVariant->belongsToCurrentCompany() && $productVariant->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك صلاحية لتحديث متغير المنتج هذا.');
            }

            DB::beginTransaction();
            try {
                $validated = $request->validated();
                $updatedBy = $authUser->id;

                // التحقق من أن المنتج الأم المحدث تابع لشركة المستخدم أو أن المستخدم super_admin
                if (isset($validated['product_id']) && $validated['product_id'] != $productVariant->product_id) {
                    $newProduct = Product::with('company')->find($validated['product_id']);
                    if (!$newProduct || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $newProduct->company_id !== $companyId)) {
                        DB::rollBack();
                        return api_forbidden('المنتج الجديد غير موجود أو غير متاح ضمن شركتك.');
                    }
                }

                // تحديد معرف الشركة للمتغير المحدث
                $variantCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validated['company_id']))
                    ? $validated['company_id']
                    : $productVariant->company_id;

                // التأكد من أن المستخدم مصرح له بتعديل متغير لشركة أخرى
                if ($variantCompanyId != $productVariant->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('لا يمكنك تغيير شركة متغير المنتج إلا إذا كنت مدير عام.');
                }

                $validated['company_id'] = $variantCompanyId;
                $validated['updated_by'] = $updatedBy;

                $productVariant->update($validated);

                // تحديث الخصائص (attributes)
                $requestedAttributeIds = collect($request->input('attributes') ?? [])
                    ->pluck('id')->filter()->all();
                $productVariant->attributes()->whereNotIn('id', $requestedAttributeIds)->delete();

                if ($request->has('attributes') && is_array($request->input('attributes'))) {
                    foreach ($request->input('attributes') as $attr) {
                        if (!empty($attr['attribute_id']) && !empty($attr['attribute_value_id'])) {
                            if (isset($attr['id'])) {
                                $existingAttr = $productVariant->attributes()->find($attr['id']);
                                if ($existingAttr) {
                                    $existingAttr->update([
                                        'attribute_id' => $attr['attribute_id'],
                                        'attribute_value_id' => $attr['attribute_value_id'],
                                        'company_id' => $variantCompanyId,
                                        'updated_by' => $authUser->id,
                                    ]);
                                }
                            } else {
                                $productVariant->attributes()->create([
                                    'attribute_id' => $attr['attribute_id'],
                                    'attribute_value_id' => $attr['attribute_value_id'],
                                    'company_id' => $variantCompanyId,
                                    'created_by' => $authUser->id,
                                ]);
                            }
                        }
                    }
                }

                // تحديث سجلات المخزون (stocks)
                $requestedStockIds = collect($request->input('stocks') ?? [])->pluck('id')->filter()->all();
                $productVariant->stocks()->whereNotIn('id', $requestedStockIds)->delete();

                if ($request->has('stocks') && is_array($request->input('stocks'))) {
                    foreach ($request->input('stocks') as $stockData) {
                        if (!empty($stockData['warehouse_id'])) {
                            Stock::updateOrCreate(
                                ['id' => $stockData['id'] ?? null, 'variant_id' => $productVariant->id],
                                [
                                    'warehouse_id' => $stockData['warehouse_id'],
                                    'quantity' => $stockData['quantity'] ?? 0,
                                    'reserved' => $stockData['reserved'] ?? 0,
                                    'min_quantity' => $stockData['min_quantity'] ?? 0,
                                    'cost' => $stockData['cost'] ?? null,
                                    'batch' => $stockData['batch'] ?? null,
                                    'expiry' => $stockData['expiry'] ?? null,
                                    'loc' => $stockData['loc'] ?? null,
                                    'status' => $stockData['status'] ?? 'available',
                                    'company_id' => $variantCompanyId,
                                    'created_by' => $stockData['created_by'] ?? $authUser->id,
                                    'updated_by' => $authUser->id,
                                ]
                            );
                        }
                    }
                } else {
                    $productVariant->stocks()->delete();
                }

                DB::commit();
                return api_success(new ProductVariantResource($productVariant->load($this->relations)), 'تم تحديث متغير المنتج بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث متغير المنتج.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث متغير المنتج.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group إدارة المتغيرات (Product Variants)
     * 
     * حذف متغير
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id ?? null;

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }
            if (!$companyId) {
                return api_forbidden('يتطلب الارتباط بالشركة.');
            }

            $productVariant = ProductVariant::with(['company', 'creator', 'stocks', 'attributes'])->findOrFail($id);

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('product_variants.delete_all'), perm_key('admin.company')])) {
                $canDelete = $productVariant->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.delete_children'))) {
                $canDelete = $productVariant->belongsToCurrentCompany() && $productVariant->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.delete_self'))) {
                $canDelete = $productVariant->belongsToCurrentCompany() && $productVariant->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك صلاحية لحذف متغير المنتج هذا.');
            }

            // التحقق من المخزون قبل الحذف
            if ($productVariant->stocks()->sum('quantity') > 0) {
                return api_error('لا يمكن حذف متغير المنتج لوجود كميات مخزنية مرتبطة به.', [], 409);
            }

            DB::beginTransaction();
            try {
                $deletedProductVariant = $productVariant->replicate();
                $deletedProductVariant->setRelations($productVariant->getRelations());

                $productVariant->stocks()->delete();
                $productVariant->attributes()->delete();
                $productVariant->delete();

                DB::commit();
                return api_success(new ProductVariantResource($deletedProductVariant), 'تم حذف متغير المنتج بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف متغير المنتج.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group إدارة المتغيرات (Product Variants)
     * 
     * حذف متغيرات متعددة
     */
    public function deleteMultiple(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id ?? null;

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }
            if (!$companyId) {
                return api_forbidden('يتطلب الارتباط بالشركة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasAnyPermission([perm_key('product_variants.delete_all'), perm_key('admin.company'), perm_key('product_variants.delete_children'), perm_key('product_variants.delete_self')])) {
                return api_forbidden('ليس لديك صلاحية لحذف متغيرات المنتجات.');
            }

            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer|exists:product_variants,id',
            ]);

            $idsToDelete = $request->input('ids');
            $cannotDelete = [];

            DB::beginTransaction();
            try {
                $deletedCount = 0;
                foreach ($idsToDelete as $id) {
                    $productVariant = ProductVariant::with(['company', 'creator', 'stocks', 'attributes'])->find($id);

                    if ($productVariant) {
                        $canDelete = false;
                        if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                            $canDelete = true;
                        } elseif ($authUser->hasAnyPermission([perm_key('product_variants.delete_all'), perm_key('admin.company')])) {
                            $canDelete = $productVariant->belongsToCurrentCompany();
                        } elseif ($authUser->hasPermissionTo(perm_key('product_variants.delete_children'))) {
                            $canDelete = $productVariant->belongsToCurrentCompany() && $productVariant->createdByUserOrChildren();
                        } elseif ($authUser->hasPermissionTo(perm_key('product_variants.delete_self'))) {
                            $canDelete = $productVariant->belongsToCurrentCompany() && $productVariant->createdByCurrentUser();
                        }

                        if ($canDelete) {
                            if ($productVariant->stocks()->sum('quantity') > 0) {
                                $cannotDelete[] = $productVariant->id;
                            } else {
                                $productVariant->stocks()->delete();
                                $productVariant->attributes()->delete();
                                $productVariant->delete();
                                $deletedCount++;
                            }
                        }
                    }
                }

                if (!empty($cannotDelete)) {
                    DB::rollBack();
                    return api_error('بعض متغيرات المنتجات لا يمكن حذفها لوجود كميات مخزنية مرتبطة بها.', ['ids_with_stock' => $cannotDelete], 409);
                }

                DB::commit();
                return api_success([], "تم حذف {$deletedCount} متغير منتج بنجاح.");
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف المتغيرات.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group إدارة المتغيرات (Product Variants)
     * 
     * بحث متقدم في التشكيلات
     */
    public function searchByProduct(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $hasStock = $request->get('has_stock', 1);
            $inSales = $request->get('in_sales');

            $query = ProductVariant::with([
                'product', 'images', 'attributes.attributeValue', 'stocks',
                'baseUnit', 'purchaseUnit', 'displayUnit', 'units.unit', 'unitPrices'
            ]);

            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            if ($authUser && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                $query->whereCompanyIsCurrent();
            }

            $query->whereHas('product', function($q) use ($inSales) {
                if ($inSales) {
                    $q->inSales();
                } else {
                    $q->where('active', true);
                }
            });

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('sku', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%")
                      ->orWhereHas('product', function($pq) use ($search) {
                          $pq->where('name', 'like', "%{$search}%");
                      });
                });
            }

            $variants = $query->take(30)->get();

            $data = $variants->map(function ($variant) use ($hasStock) {
                $product = $variant->product;
                
                if (!$product) return null;

                $activeBranchId = config('app.active_branch_id') ?? (Auth::check() ? Auth::user()->branch_id : null);
                
                $availableStocks = $variant->stocks->where('status', 'available');
                
                // Filter stocks by branch if a branch context exists
                if ($activeBranchId) {
                    $availableStocks = $availableStocks->filter(function($stock) use ($activeBranchId) {
                        return $stock->branch_id == $activeBranchId || ($stock->warehouse && $stock->warehouse->branch_id == $activeBranchId);
                    });
                }

                $quantity = $product->require_stock ? $availableStocks->sum('quantity') : 999999;
                
                if ($hasStock && $product->require_stock && $quantity <= 0) {
                    return null;
                }

                return [
                    'id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => $product->name,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'price' => $variant->retail_price,
                    'retail_price' => $variant->retail_price,
                    'wholesale_price' => $variant->wholesale_price,
                    'purchase_price' => $variant->purchase_price,
                    'quantity' => $quantity,
                    'requires_stock' => $product->require_stock,
                    'product_type' => $product->product_type,
                    'primary_image_url' => $variant->primary_image_url,
                    'attributes' => $variant->attributes->map(function($attr) {
                        return [
                            'attribute_value' => [
                                'name' => $attr->attributeValue->name ?? null
                            ]
                        ];
                    }),
                    // تفاصيل وحدات القياس
                    'base_unit_id' => $variant->base_unit_id,
                    'purchase_unit_id' => $variant->purchase_unit_id,
                    'display_unit_id' => $variant->display_unit_id,
                    'allow_decimal_quantities' => (bool)$product->allow_decimal_quantities,
                    'quantity_precision' => (int)$product->quantity_precision,
                    'base_unit' => $variant->baseUnit ? [
                        'id' => $variant->baseUnit->id,
                        'name' => $variant->baseUnit->name,
                        'code' => $variant->baseUnit->code,
                        'decimal_places' => $variant->baseUnit->decimal_places,
                    ] : null,
                    'purchase_unit' => $variant->purchaseUnit ? [
                        'id' => $variant->purchaseUnit->id,
                        'name' => $variant->purchaseUnit->name,
                        'code' => $variant->purchaseUnit->code,
                        'decimal_places' => $variant->purchaseUnit->decimal_places,
                    ] : null,
                    'display_unit' => $variant->displayUnit ? [
                        'id' => $variant->displayUnit->id,
                        'name' => $variant->displayUnit->name,
                        'code' => $variant->displayUnit->code,
                        'decimal_places' => $variant->displayUnit->decimal_places,
                    ] : null,
                    'units' => $variant->units->map(function($vu) {
                        return [
                            'unit_id' => $vu->unit_id,
                            'conversion_factor_to_base' => (float)$vu->conversion_factor_to_base,
                            'is_default' => (bool)$vu->is_default,
                            'unit' => $vu->unit ? [
                                'id' => $vu->unit->id,
                                'name' => $vu->unit->name,
                                'code' => $vu->unit->code,
                                'decimal_places' => $vu->unit->decimal_places,
                            ] : null,
                        ];
                    })->toArray(),
                    'unit_prices' => $variant->unitPrices->map(function($up) {
                        return [
                            'unit_id' => $up->unit_id,
                            'price' => (float)$up->price,
                            'cost' => (float)$up->cost,
                        ];
                    })->toArray(),
                ];
            })->filter()->values();

            return api_success($data);

        } catch (\Throwable $e) {
            Log::error('Search by product error: ' . $e->getMessage());
            return api_exception($e);
        }
    }
}
