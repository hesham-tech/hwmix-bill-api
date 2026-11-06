<?php
namespace Modules\Store\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Store\Services\StoreGeography;
class QuoteOrderRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'checkout_key'=>'required|uuid','payment_method'=>'required|in:cod,online',
            'shipping_address_id'=>'nullable|required_without:guest_address|integer',
            'guest_address'=>'nullable|required_without:shipping_address_id|array',
            'guest_address.recipient_name'=>'required_with:guest_address|string|max:150',
            'guest_address.phone'=>['required_with:guest_address','string','regex:/^(?:\\+?20|0)1[0125][0-9]{8}$/'],
            'guest_address.governorate_code'=>['required_with:guest_address',Rule::in(array_keys(StoreGeography::governorates()))],
            'guest_address.city'=>'required_with:guest_address|string|max:100',
            'guest_address.district'=>'nullable|string|max:100','guest_address.street'=>'required_with:guest_address|string|max:255',
            'guest_address.building'=>'nullable|string|max:50','guest_address.floor'=>'nullable|string|max:50',
            'guest_address.apartment'=>'nullable|string|max:50','guest_address.landmark'=>'nullable|string|max:500',
            'notes'=>'nullable|string|max:2000','items'=>'required|array|min:1|max:100',
            'items.*.product_id'=>'required|integer|min:1','items.*.variant_id'=>'required|integer|min:1|distinct',
            'items.*.quantity'=>'required|integer|min:1|max:10000',
        ];
    }
}
