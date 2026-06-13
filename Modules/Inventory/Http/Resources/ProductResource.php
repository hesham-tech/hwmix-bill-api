<?php

namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use App\Http\Resources\User\UserBasicResource;
use App\Http\Resources\Company\CompanyResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Image\ImageResource;
use App\Http\Resources\InstallmentPlan\InstallmentPlanBasicResource;
use Modules\Inventory\Models\ProductVariant;
use Modules\Inventory\Http\Resources\UnitResource;

/**
 * متحور البيانات للمنتجات
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'active' => (bool) $this->active,
            'is_active_in_store' => (bool) $this->is_active_in_store,
            'is_active_in_sales' => (bool) $this->is_active_in_sales,
            'featured' => (bool) $this->featured,
            'returnable' => (bool) $this->returnable,
            'desc' => $this->desc,
            'desc_long' => $this->desc_long,
            'product_type' => $this->product_type,
            'require_stock' => (bool) $this->require_stock,
            'is_downloadable' => (bool) $this->is_downloadable,
            'download_url' => $this->download_url,
            'download_limit' => $this->download_limit,
            'license_keys' => $this->license_keys,
            'available_keys_count' => (int) $this->available_keys_count,
            'validity_days' => $this->validity_days,
            'expires_at' => $this->expires_at?->format('Y-m-d H:i:s'),
            'delivery_instructions' => $this->delivery_instructions,
            'category_id' => $this->category_id,
            'brand_id' => $this->whenNotNull($this->brand_id),
            'company_id' => $this->company_id,
            'base_unit_id' => $this->base_unit_id,
            'purchase_unit_id' => $this->purchase_unit_id,
            'display_unit_id' => $this->display_unit_id,
            'allow_decimal_quantities' => (bool) $this->allow_decimal_quantities,
            'quantity_precision' => (int) $this->quantity_precision,
            'base_unit' => new UnitResource($this->whenLoaded('baseUnit')),
            'purchase_unit' => new UnitResource($this->whenLoaded('purchaseUnit')),
            'display_unit' => new UnitResource($this->whenLoaded('displayUnit')),
            'total_available_quantity' => (float) ($this->total_available_quantity ?? 0),
            'min_price' => (float) ($this->variants_min_retail_price ?? 0),
            'max_price' => (float) ($this->variants_max_retail_price ?? 0),
            'price_range' => $this->relationLoaded('variants') 
                ? (float) ($this->variants->sortByDesc('id')->first()?->retail_price ?? 0)
                : (float) (ProductVariant::where('product_id', $this->id)->latest('id')->value('retail_price') ?? 0),
            'avg_purchase_price' => $this->when(
                auth()->user()?->hasAnyPermission([perm_key('products.view_purchase_price'), 'admin.super', 'admin.company']),
                (float) ($this->relationLoaded('variants') 
                    ? $this->variants->avg('purchase_price') 
                    : $this->variants()->avg('purchase_price') ?? 0)
            ),
            'avg_wholesale_price' => $this->when(
                auth()->user()?->hasAnyPermission([perm_key('products.view_wholesale_price'), 'admin.super', 'admin.company']),
                (float) ($this->relationLoaded('variants') 
                    ? $this->variants->avg('wholesale_price') 
                    : $this->variants()->avg('wholesale_price') ?? 0)
            ),
            'avg_retail_price' => (float) ($this->relationLoaded('variants') 
                ? $this->variants->avg('retail_price') 
                : $this->variants()->avg('retail_price') ?? 0),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'installment_plan' => new InstallmentPlanBasicResource($this->whenLoaded('installmentPlan')),
            'creator' => new UserBasicResource($this->whenLoaded('creator')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'variants' => $this->whenLoaded('variants', fn() => ProductVariantResource::collection($this->variants)),
            'images' => $this->whenLoaded('images', fn() => ImageResource::collection($this->images)),
            'primary_image_url' => $this->primary_image_url,
            'tags' => $this->tags ?? [],
            'sales_count' => (int) ($this->sales_count ?? 0),
            'published_at' => $this->whenNotNull($this->published_at ? $this->published_at->format('Y-m-d H:i:s') : null),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
