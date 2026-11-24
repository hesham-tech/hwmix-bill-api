<?php
namespace Modules\AiPlatform\Http\Requests\Admin;

final class AiAccountRequest extends AiAdminWriteRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes|required';
        return [
            'ai_provider_id' => $required.'|integer|exists:ai_providers,id',
            'label' => $required.'|string|max:150',
            'api_key_encrypted' => $this->isMethod('POST') ? 'required|string|max:16384' : 'nullable|string|max:16384',
            'custom_base_url' => 'nullable|url|max:300', 'priority' => 'nullable|integer|min:0|max:127',
        ];
    }
}
