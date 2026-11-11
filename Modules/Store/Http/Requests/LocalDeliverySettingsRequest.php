<?php
namespace Modules\Store\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class LocalDeliverySettingsRequest extends FormRequest {
    public function authorize(): bool {return true;}
    public function rules(): array {return [
        'enabled'=>'required|boolean','radius_km'=>'required_if:enabled,true|nullable|decimal:0,3|between:0.001,20000',
        'fee'=>'required_if:enabled,true|nullable|decimal:0,2|min:0|max:9999999999.99','free_above'=>'nullable|decimal:0,2|min:0|max:9999999999.99',
    ];}
}
