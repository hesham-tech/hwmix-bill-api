<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use App\Http\Resources\Brand\BrandResource;
use App\Http\Resources\Stock\StockResource;
use App\Http\Resources\User\UserBasicResource;
use App\Http\Resources\Company\CompanyResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Category\CategoryResource;
use App\Http\Resources\Warehouse\WarehouseResource;
use App\Http\Resources\Image\ImageResource;
use App\Http\Resources\ProductVariant\ProductVariantResource;
use App\Http\Resources\InstallmentPlan\InstallmentPlanBasicResource;
use App\Http\Resources\ProductVariantAttribute\ProductVariantAttributeResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'active' => (bool) $this->active,
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
            'total_available_quantity' => (float) ($this->total_available_quantity ?? 0),
            'min_price' => (float) ($this->variants_min_retail_price ?? 0),
            'max_price' => (float) ($this->variants_max_retail_price ?? 0),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'installment_plan' => new InstallmentPlanBasicResource($this->whenLoaded('installmentPlan')),
            'creator' => new UserBasicResource($this->whenLoaded('creator')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'images' => ImageResource::collection($this->whenLoaded('images')), // Polymorphic images
            'primary_image_url' => $this->primary_image_url,
            'tags' => $this->tags ?? [],
            'published_at' => $this->whenNotNull($this->published_at ? $this->published_at->format('Y-m-d H:i:s') : null),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
