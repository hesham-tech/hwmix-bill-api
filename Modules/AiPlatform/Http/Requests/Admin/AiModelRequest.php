<?php
namespace Modules\AiPlatform\Http\Requests\Admin;

final class AiModelRequest extends AiAdminWriteRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes|required';
        return [
            'ai_provider_id' => $required.'|integer|exists:ai_providers,id',
            'model_id' => $required.'|string|max:100', 'label' => $required.'|string|max:255',
            'version' => 'nullable|string|max:100',
            'max_context_tokens' => 'nullable|integer|min:1', 'max_output_tokens' => 'nullable|integer|min:1',
            'input_price_per_1k' => 'nullable|numeric|min:0', 'output_price_per_1k' => 'nullable|numeric|min:0',
        ];
    }
}
