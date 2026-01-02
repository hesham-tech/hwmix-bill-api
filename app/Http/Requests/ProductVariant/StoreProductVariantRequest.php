<?php

namespace App\Http\Requests\ProductVariant;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductVariantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'barcode' => 'nullable|string|unique:product_variants,barcode,' . $this->id,
            'sku' => 'nullable|string|unique:product_variants,sku,' . $this->id,
            'retail_price' => 'nullable|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'profit_margin' => 'nullable|numeric|min:0|max:100',
            'image' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|string|max:255',
            'tax' => 'nullable|numeric|min:0|max:100',
            'discount' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive,discontinued',
            'product_id' => 'required|exists:products,id',
            'company_id' => 'required|exists:companies,id',
            'created_by' => 'required|exists:users,id',
            'attributes' => 'nullable|array',
            'attributes.*.attribute_id' => 'required|exists:attributes,id',
            'attributes.*.attribute_value_id' => 'required|exists:attribute_values,id',
            'stocks' => 'nullable|array',
            'stocks.*.warehouse_id' => 'required|exists:warehouses,id',
            'stocks.*.quantity' => 'nullable|integer|min:0',
            'stocks.*.min_quantity' => 'nullable|integer|min:0',
            'stocks.*.cost' => 'nullable|numeric|min:0',
        ];
    }
}
