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
            'shipping_address_id' => 'required_without:guest_address|nullable|exists:customer_addresses,id',
            
            // Guest address validation
            'guest_address' => 'required_without:shipping_address_id|array',
            'guest_address.recipient_name' => 'required_with:guest_address|string|max:255',
            'guest_address.phone' => 'required_with:guest_address|string|max:20',
            'guest_address.city' => 'required_with:guest_address|string|max:100',
            'guest_address.street' => 'required_with:guest_address|string|max:255',
            'guest_address.district' => 'nullable|string|max:100',
            'guest_address.building' => 'nullable|string|max:255',
            'guest_address.floor' => 'nullable|string|max:255',
            'guest_address.apartment' => 'nullable|string|max:255',
            'guest_address.landmark' => 'nullable|string|max:255',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }
}
