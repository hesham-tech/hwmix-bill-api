<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Inventory\DTOs\ProductData;

class UpdateProductRequest extends FormRequest
{
    public function authorize()
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

    public function rules()
    {
        $productId = $this->route('product')->id ?? null;

        $rules = [
            'name' => 'sometimes|string|max:255',
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
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($productId),
            ],
            'published_at' => 'sometimes|nullable|date',
            'desc' => 'sometimes|nullable|string',
            'desc_long' => 'sometimes|nullable|string',
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'brand_id' => 'sometimes|nullable|exists:brands,id',
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'created_by' => 'sometimes|nullable|exists:users,id',
            'active' => 'sometimes|boolean',
            'featured' => 'sometimes|boolean',
            'returnable' => 'sometimes|boolean',
            'base_unit_id' => 'nullable|integer|exists:units,id',
            'purchase_unit_id' => 'nullable|integer|exists:units,id',
            'display_unit_id' => 'nullable|integer|exists:units,id',
            'allow_decimal_quantities' => 'sometimes|boolean',
            'quantity_precision' => 'sometimes|integer|min:0|max:6',
            'variants' => 'sometimes|array|min:1',
            'variants.*.id' => 'sometimes|nullable|integer|exists:product_variants,id',
            'variants.*.retail_price' => 'sometimes|numeric|min:0',
            'variants.*.wholesale_price' => 'sometimes|nullable|numeric|min:0',
            'variants.*.purchase_price' => 'sometimes|nullable|numeric|min:0',
            'variants.*.profit_margin' => 'sometimes|nullable|numeric',
            'variants.*.image' => 'sometimes|nullable|string|max:255',
            'variants.*.weight' => 'sometimes|nullable|numeric|min:0',
            'variants.*.dimensions' => 'sometimes|nullable|string|max:255',
            'variants.*.tax' => 'sometimes|nullable|numeric|min:0|max:100',
            'variants.*.discount' => 'sometimes|nullable|numeric|min:0',
            'variants.*.min_quantity' => 'sometimes|nullable|integer|min:0',
            'variants.*.status' => 'sometimes|nullable|string|in:active,inactive,discontinued',
            'variants.*.created_by' => 'sometimes|nullable|exists:users,id',
            'variants.*.company_id' => 'sometimes|nullable|exists:companies,id',
            'variants.*.base_unit_id' => 'nullable|integer|exists:units,id',
            'variants.*.purchase_unit_id' => 'nullable|integer|exists:units,id',
            'variants.*.display_unit_id' => 'nullable|integer|exists:units,id',
            'variants.*.units' => 'sometimes|array',
            'variants.*.units.*.unit_id' => 'required|integer|exists:units,id',
            'variants.*.units.*.conversion_factor_to_base' => 'required|numeric|min:0',
            'variants.*.units.*.is_default' => 'sometimes|boolean',
            'variants.*.units.*.min_quantity' => 'nullable|numeric|min:0',
            'variants.*.units.*.max_quantity' => 'nullable|numeric|min:0',
            'variants.*.units.*.allow_fraction' => 'sometimes|boolean',
            'variants.*.unit_prices' => 'sometimes|array',
            'variants.*.unit_prices.*.unit_id' => 'required|integer|exists:units,id',
            'variants.*.unit_prices.*.price' => 'required|numeric|min:0',
            'variants.*.unit_prices.*.cost' => 'nullable|numeric|min:0',
            'variants.*.unit_prices.*.effective_from' => 'nullable|date',
            'variants.*.unit_prices.*.effective_to' => 'nullable|date|after_or_equal:variants.*.unit_prices.*.effective_from',
            'variants.*.unit_prices.*.is_default' => 'sometimes|boolean',
            'variants.*.image_ids' => 'sometimes|array',
            'variants.*.image_ids.*' => 'integer|exists:images,id',
            'variants.*.primary_image_id' => 'nullable|integer|exists:images,id',
            'variants.*.attributes' => 'sometimes|nullable|array',
            'variants.*.attributes.*.attribute_id' => 'sometimes|nullable|exists:attributes,id',
            'variants.*.attributes.*.attribute_value_id' => 'sometimes|nullable|exists:attribute_values,id',
            'variants.*.stocks' => 'required|array|min:1',
            'variants.*.stocks.*.id' => 'sometimes|nullable|exists:stocks,id',
            'variants.*.stocks.*.quantity' => 'sometimes|nullable|integer|min:0',
            'variants.*.stocks.*.reserved' => 'sometimes|nullable|integer|min:0',
            'variants.*.stocks.*.expiry' => 'sometimes|nullable|date',
            'variants.*.stocks.*.status' => 'sometimes|nullable|string|in:available,unavailable,expired',
            'variants.*.stocks.*.batch' => 'sometimes|nullable|string|max:255',
            'variants.*.stocks.*.cost' => 'sometimes|nullable|numeric|min:0',
            'variants.*.stocks.*.loc' => 'sometimes|nullable|string|max:255',
            'variants.*.stocks.*.warehouse_id' => 'sometimes|nullable|exists:warehouses,id',
            'variants.*.stocks.*.company_id' => 'sometimes|nullable|exists:companies,id',
            'variants.*.stocks.*.created_by' => 'sometimes|nullable|exists:users,id',
            'variants.*.stocks.*.updated_by' => 'sometimes|nullable|exists:users,id',
        ];

        foreach ($this->input('variants', []) as $index => $variant) {
            $variantId = $variant['id'] ?? null;

            $rules["variants.{$index}.barcode"] = [
                'nullable',
                'string',
                'max:255',
                Rule::unique('product_variants', 'barcode')->ignore($variantId),
            ];

            $rules["variants.{$index}.sku"] = [
                'nullable',
                'string',
                'max:255',
                Rule::unique('product_variants', 'sku')->ignore($variantId),
            ];
        }

        return $rules;
    }

    public function toSimpleProductData(): ProductData
    {
        return ProductData::fromArray($this->validated());
    }
}
