<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Decode JSON strings if sent via FormData
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->variants)) {
            $this->merge([
                'variants' => json_decode($this->variants, true)
            ]);
        }
        if (is_string($this->tags)) {
            $this->merge([
                'tags' => json_decode($this->tags, true)
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'product_type' => 'required|in:physical,digital,service,subscription',
            'require_stock' => 'required|boolean',
            'is_downloadable' => 'required_if:product_type,digital|boolean',
            'download_url' => 'nullable|string',
            'download_limit' => 'nullable|integer|min:0',
            'license_keys' => 'nullable|array',
            'validity_days' => 'nullable|integer|min:0',
            'expires_at' => 'nullable|date',
            'delivery_instructions' => 'nullable|string',
            'image_ids' => 'sometimes|array',
            'image_ids.*' => 'integer|exists:images,id',
            'primary_image_id' => 'nullable|integer|exists:images,id',
            'published_at' => 'sometimes|nullable|date',
            'desc' => 'sometimes|nullable|string',
            'desc_long' => 'sometimes|nullable|string',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'sometimes|nullable|exists:brands,id',
            'active' => 'sometimes|boolean',
            'featured' => 'sometimes|boolean',
            'returnable' => 'sometimes|boolean',
            'variants' => 'required|array|min:1',
            'variants.*.id' => 'sometimes|nullable|integer|exists:product_variants,id',
            'variants.*.retail_price' => 'required|numeric|min:0',
            'variants.*.wholesale_price' => 'sometimes|nullable|numeric|min:0',
            'variants.*.purchase_price' => 'sometimes|nullable|numeric|min:0',
            'variants.*.profit_margin' => 'sometimes|nullable|numeric',
            'variants.*.tax' => 'sometimes|nullable|numeric|min:0|max:100',
            'variants.*.discount' => 'sometimes|nullable|numeric|min:0',
            'variants.*.stocks' => 'required_if:require_stock,true|array',
            'variants.*.stocks.*.id' => 'sometimes|nullable|integer|exists:stocks,id',
            'variants.*.stocks.*.quantity' => 'required|integer|min:0',
            'variants.*.stocks.*.warehouse_id' => 'required|exists:warehouses,id',
            'variants.*.image_ids' => 'sometimes|array',
            'variants.*.image_ids.*' => 'integer|exists:images,id',
            'variants.*.primary_image_id' => 'nullable|integer|exists:images,id',
        ];
    }

    /**
     * Map the request data to a ProductData DTO
     */
    public function toSimpleProductData(): \App\DTOs\ProductData
    {
        return \App\DTOs\ProductData::fromArray($this->validated());
    }
}
