<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => 'required|integer|min:0',
            'reserved' => 'nullable|integer|min:0',
            'min_quantity' => 'nullable|integer|min:0',
            'cost' => 'nullable|numeric|min:0',
            'batch' => 'nullable|string|max:255',
            'expiry' => 'nullable|date',
            'loc' => 'nullable|string|max:255',
            'status' => 'required|in:available,unavailable,expired',
            'variant_id' => 'required|exists:product_variants,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'company_id' => 'required|exists:companies,id',
            'created_by' => 'required|exists:users,id',
        ];
    }
}
