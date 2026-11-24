<?php
namespace Modules\AiPlatform\Http\Requests\Admin;

final class AiAgentRequest extends AiAdminWriteRequest
{
    public function rules(): array
    {
        return [
            'key' => ($this->isMethod('POST') ? 'required' : 'sometimes|required').'|string|max:100',
            'name' => 'nullable|string|max:150', 'label' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:10000', 'system_prompt' => 'nullable|string|max:50000',
            'preferred_capability' => 'nullable|string|max:100', 'is_active' => 'sometimes|boolean',
        ];
    }
}
