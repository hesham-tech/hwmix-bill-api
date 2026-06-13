<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Inventory\DTOs\ProductData;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->variants)) {
            $this->merge([
                'variants' => json_decode($this->variants, true)
            ]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'product_type' => 'sometimes|in:physical,digital,service,subscription',
            'require_stock' => 'sometimes|boolean',
            'is_downloadable' => 'sometimes|boolean',
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
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'sometimes|nullable|exists:brands,id',
            'active' => 'sometimes|boolean',
            'featured' => 'sometimes|boolean',
            'returnable' => 'sometimes|boolean',
            'variants' => 'sometimes|array',
            'variants.*.id' => 'sometimes|nullable|integer|exists:product_variants,id',
            'variants.*.retail_price' => 'sometimes|numeric|min:0',
            'variants.*.wholesale_price' => 'sometimes|nullable|numeric|min:0',
            'variants.*.purchase_price' => 'sometimes|nullable|numeric|min:0',
            'variants.*.profit_margin' => 'sometimes|nullable|numeric',
            'variants.*.tax' => 'sometimes|nullable|numeric|min:0|max:100',
            'variants.*.discount' => 'sometimes|nullable|numeric|min:0',
            'variants.*.stocks' => 'sometimes|array',
            'variants.*.stocks.*.id' => 'sometimes|nullable|integer|exists:stocks,id',
            'variants.*.stocks.*.quantity' => 'sometimes|integer|min:0',
            'variants.*.stocks.*.warehouse_id' => 'sometimes|nullable|exists:warehouses,id',
            'variants.*.image_ids' => 'sometimes|array',
            'variants.*.image_ids.*' => 'integer|exists:images,id',
            'variants.*.primary_image_id' => 'nullable|integer|exists:images,id',
        ];

        foreach ($this->input('variants', []) as $index => $variant) {
            $rules["variants.{$index}.barcode"] = [
                'nullable',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('product_variants', 'barcode'),
            ];

            $rules["variants.{$index}.sku"] = [
                'nullable',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('product_variants', 'sku'),
            ];
        }

        return $rules;
    }

    public function toSimpleProductData(): ProductData
    {
        return ProductData::fromArray($this->validated());
    }
}
