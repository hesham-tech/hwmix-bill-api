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
        $query = $this->catalogQuery();
        $price = $this->displayPriceQuery();
        $query->select('products.*')->selectSub($price, 'store_display_price');
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

        if (!empty($filters['in_stock'])) {
            $query->whereHas('variants',fn($q)=>$q->withoutGlobalScopes()->whereColumn('product_variants.company_id','products.company_id')->where('status','active')->where('retail_price','>',0)
                ->whereRaw($this->availableBaseSql().' >= '.$this->factorSql()));
        }
        foreach(['price_min'=>'>=','price_max'=>'<='] as $key=>$operator) {
            if(isset($filters[$key]) && $filters[$key] !== '') $query->whereRaw('('.$price->toSql().') '.$operator.' CAST(? AS DECIMAL(20,2))',array_merge($price->getBindings(),[$filters[$key]]));
        }

        // الترتيب
        match ($filters['sort'] ?? 'newest') {
            'price_asc'    => $query->orderBy('store_display_price')->orderBy('products.id'),
            'price_desc'   => $query->orderByDesc('store_display_price')->orderBy('products.id'),
            'best_selling' => $query->orderByDesc('sales_count'),
            default        => $query->orderByDesc('created_at'),
        };

        $perPage = max(1,min((int) ($filters['per_page'] ?? 12), 48));
        return $query->paginate($perPage);
    }

    /**
     * جلب تفاصيل منتج واحد بالـ id
     */
    public function getProductById(int $id): ?Product
    {
        return $this->catalogQuery()->where('products.id',$id)->first();
    }

    private function availableBaseSql(): string {
        $date = now('Africa/Cairo')->toDateString();
        return "(SELECT COALESCE(SUM(stocks.quantity-stocks.reserved),0) FROM stocks INNER JOIN store_seller_profiles sp ON sp.company_id=stocks.company_id AND sp.warehouse_id=stocks.warehouse_id AND sp.is_enabled=1 WHERE stocks.variant_id=product_variants.id AND stocks.company_id=product_variants.company_id AND stocks.deleted_at IS NULL AND stocks.status='available' AND (stocks.expiry IS NULL OR DATE(stocks.expiry)>='$date'))";
    }
    private function factorSql(): string {
        $unit='COALESCE(product_variants.display_unit_id,products.display_unit_id,product_variants.base_unit_id,products.base_unit_id)';
        $base='COALESCE(product_variants.base_unit_id,products.base_unit_id)';
        return "(CASE WHEN $base IS NULL THEN NULL WHEN $unit=$base THEN 1 ELSE (SELECT CASE WHEN conversion_factor_to_base>0 THEN conversion_factor_to_base ELSE NULL END FROM product_variant_units WHERE product_variant_id=product_variants.id AND unit_id=$unit LIMIT 1) END)";
    }
    private function displayPriceQuery() {
        return \Illuminate\Support\Facades\DB::table('product_variants')->whereColumn('product_id','products.id')->whereColumn('company_id','products.company_id')
            ->where('status','active')->where('retail_price','>',0)
            ->selectRaw('COALESCE(MIN(CASE WHEN '.$this->availableBaseSql().' >= '.$this->factorSql().' THEN retail_price END),MIN(retail_price))');
    }
    private function catalogQuery() {
        return Product::withoutGlobalScopes()
            ->whereHas('company',fn($q)=>$q->storePublishEnabled())->where('is_active_in_store',true)->where('active',true)
            ->with(['images','category','brand','baseUnit'=>fn($q)=>$q->withoutGlobalScopes(),'displayUnit'=>fn($q)=>$q->withoutGlobalScopes(),'company.logo',
                'variants'=>fn($q)=>$q->withoutGlobalScopes()->where('status','active')->where('retail_price','>',0)
                    ->with(['images','baseUnit'=>fn($q)=>$q->withoutGlobalScopes(),'displayUnit'=>fn($q)=>$q->withoutGlobalScopes(),'units.unit'=>fn($q)=>$q->withoutGlobalScopes(),'attributes.attribute','attributes.attributeValue',
                        'stocks'=>fn($sq)=>$sq->withoutGlobalScopes()->whereNull('deleted_at')->where('status','available')
                            ->where(fn($eq)=>$eq->whereNull('expiry')->orWhereDate('expiry','>=',now('Africa/Cairo')->toDateString()))
                            ->whereExists(fn($profile)=>$profile->selectRaw('1')->from('store_seller_profiles')->whereColumn('company_id','stocks.company_id')->whereColumn('warehouse_id','stocks.warehouse_id')->where('is_enabled',true))])]);
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
            ->with('image')
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
        $ids=array_column($items,'variant_id');
        $products=$this->catalogQuery()->whereHas('variants',fn($q)=>$q->withoutGlobalScopes()->whereIn('id',$ids))->get();
        $variants=[];
        foreach($products as $product) {
            $data=(new \Modules\Store\Transformers\StoreProductResource($product))->resolve(request());
            foreach($data['variants'] as $variant)$variants[$variant['id']]=$variant+['product_id'=>$product->id];
        }
        $errors=[];
        foreach($items as $item) {
            $variant=$variants[$item['variant_id']]??null;
            if(!$variant || (isset($item['product_id'])&&(int)$item['product_id']!==$variant['product_id']))
                $errors[]=['variant_id'=>$item['variant_id'],'error'=>'المنتج لم يعد متاحًا للشراء'];
            elseif($variant['available_stock']<(int)$item['quantity'])
                $errors[]=['variant_id'=>$item['variant_id'],'error'=>'الكمية المطلوبة غير متوفرة بوحدة البيع','available'=>$variant['available_stock']];
        }
        return $errors;
    }
}
