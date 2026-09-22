<?php

namespace Modules\Store\Services;

// خدمة استعلامات منتجات المتجر العام — تجلب المنتجات بدون قيود الشركة وتدعم الفلترة والبحث
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\Brand;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

class StoreProductQueryService
{
    /**
     * جلب قائمة منتجات المتجر مع الفلاتر
     */
    public function getProducts(array $filters = []): LengthAwarePaginator
    {
        $query = Product::withoutGlobalScopes()
            ->whereHas('company', fn($q) => $q->storePublishEnabled())
            ->where('is_active_in_store', true)
            ->where('active', true)
            ->with([
                'images',
                'category',
                'brand',
                'company.logo',
                'variants' => fn($q) => $q->withoutGlobalScopes()
                    ->where('status', 'active')
                    ->with(['stocks' => fn($sq) => $sq->withoutGlobalScopes()]),
            ]);

        // فلتر مصفوفة أرقام المنتجات (للمفضلة مثلاً)
        if (!empty($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        // فلتر البحث النصي
        if (!empty($filters['q'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['q'] . '%')
                  ->orWhere('desc', 'like', '%' . $filters['q'] . '%');
            });
        }

        // فلتر القسم
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // فلتر الماركة
        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        // فلتر البائع (الشركة)
        if (!empty($filters['vendor_id'])) {
            $query->where('company_id', $filters['vendor_id']);
        }

        // فلتر المميز
        if (!empty($filters['featured'])) {
            $query->where('featured', true);
        }

        // فلتر المتاح في المخزون
        if (!empty($filters['in_stock'])) {
            $query->whereHas('variants.stocks', function ($q) {
                $q->withoutGlobalScopes()
                  ->whereRaw('(quantity - reserved) > 0');
            });
        }

        // فلتر نطاق السعر — بناءً على variant prices
        if (!empty($filters['price_min'])) {
            $query->whereHas('variants', fn($q) =>
                $q->withoutGlobalScopes()->where('retail_price', '>=', $filters['price_min'])
            );
        }
        if (!empty($filters['price_max'])) {
            $query->whereHas('variants', fn($q) =>
                $q->withoutGlobalScopes()->where('retail_price', '<=', $filters['price_max'])
            );
        }

        // الترتيب
        match ($filters['sort'] ?? 'newest') {
            'price_asc'    => $query->orderBy('id'),
            'price_desc'   => $query->orderByDesc('id'),
            'best_selling' => $query->orderByDesc('sales_count'),
            default        => $query->orderByDesc('created_at'),
        };

        $perPage = min((int) ($filters['per_page'] ?? 12), 48);
        return $query->paginate($perPage);
    }

    /**
     * جلب تفاصيل منتج واحد بالـ id
     */
    public function getProductById(int $id): ?Product
    {
        return Product::withoutGlobalScopes()
            ->where('id', $id)
            ->where('is_active_in_store', true)
            ->where('active', true)
            ->whereHas('company', fn($q) => $q->storePublishEnabled())
            ->with([
                'images',
                'category',
                'brand',
                'company.logo',
                'variants' => fn($q) => $q->withoutGlobalScopes()
                    ->where('status', 'active')
                    ->with([
                        'images',
                        'attributes.attribute',
                        'attributes.attributeValue',
                        'stocks' => fn($sq) => $sq->withoutGlobalScopes(),
                    ]),
            ])
            ->first();
    }

    /**
     * جلب أقسام المتجر (التي تحتوي منتجات نشطة)
     */
    public function getCategories(): \Illuminate\Support\Collection
    {
        $activeProductIds = Product::withoutGlobalScopes()
            ->whereHas('company', fn($q) => $q->storePublishEnabled())
            ->where('is_active_in_store', true)
            ->where('active', true)
            ->pluck('category_id')
            ->filter()
            ->unique();

        return Category::withoutGlobalScopes()
            ->whereIn('id', $activeProductIds)
            ->where('active', true)
            ->withCount(['products' => fn($q) =>
                $q->withoutGlobalScopes()
                  ->where('is_active_in_store', true)
                  ->where('active', true)
            ])
            ->orderByDesc('products_count')
            ->get();
    }

    /**
     * جلب ماركات المتجر
     */
    public function getBrands(): \Illuminate\Support\Collection
    {
        $brandIds = Product::withoutGlobalScopes()
            ->whereHas('company', fn($q) => $q->storePublishEnabled())
            ->where('is_active_in_store', true)
            ->where('active', true)
            ->pluck('brand_id')
            ->filter()
            ->unique();

        return Brand::withoutGlobalScopes()
            ->whereIn('id', $brandIds)
            ->where('active', true)
            ->get(['id', 'name']);
    }

    /**
     * جلب البائعين (الشركات المؤهلة للمتجر)
     */
    public function getVendors(): \Illuminate\Support\Collection
    {
        return Company::withoutGlobalScopes()
            ->storePublishEnabled()
            ->withCount(['products' => fn($q) =>
                $q->withoutGlobalScopes()
                  ->where('is_active_in_store', true)
                  ->where('active', true)
            ])
            ->having('products_count', '>', 0)
            ->with('logo')
            ->get();
    }

    /**
     * جلب بيانات بائع واحد
     */
    public function getVendor(int $companyId): ?Company
    {
        return Company::withoutGlobalScopes()
            ->where('id', $companyId)
            ->storePublishEnabled()
            ->with(['logo', 'images'])
            ->first();
    }

    /**
     * جلب منتجات بائع محدد
     */
    public function getVendorProducts(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $filters['vendor_id'] = $companyId;
        return $this->getProducts($filters);
    }

    /**
     * التحقق من صلاحية عناصر السلة (مخزون كافٍ؟)
     */
    public function validateCartItems(array $items): array
    {
        $errors = [];
        foreach ($items as $item) {
            $variant = \Modules\Inventory\Models\ProductVariant::withoutGlobalScopes()
                ->find($item['variant_id']);

            if (!$variant) {
                $errors[] = ['variant_id' => $item['variant_id'], 'error' => 'المنتج غير موجود'];
                continue;
            }

            $availableStock = $variant->stocks()->withoutGlobalScopes()
                ->selectRaw('SUM(quantity - reserved) as available')
                ->value('available') ?? 0;

            if ($availableStock < $item['quantity']) {
                $errors[] = [
                    'variant_id' => $item['variant_id'],
                    'error'      => 'الكمية المطلوبة غير متوفرة',
                    'available'  => $availableStock,
                ];
            }
        }
        return $errors;
    }
}
