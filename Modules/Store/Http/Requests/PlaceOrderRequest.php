<?php

namespace Modules\Store\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرح له باتخاذ هذا الطلب
     */
    public function authorize()
    {
        return true;
    }

    /**
     * قواعد التحقق الخاصة بالطلب
     */
    public function rules()
    {
        return [
            'payment_method' => 'required|string|in:cash_on_delivery,credit_card,hwnix_cash',
            'shipping_address_id' => 'required|exists:customer_addresses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }
}
