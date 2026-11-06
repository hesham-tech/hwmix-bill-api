<?php
namespace Modules\Store\Http\Requests;
class PlaceOrderRequest extends QuoteOrderRequest {
    public function rules(): array {
        return array_merge(parent::rules(),[
            'quote_id'=>'required|uuid','legal_accepted'=>'required|accepted',
            'legal_version_ids'=>'required|array|min:1','legal_version_ids.*'=>'required|integer|distinct',
            'payment_gateway_id'=>'required_if:payment_method,online|nullable|integer',
        ]);
    }
}
