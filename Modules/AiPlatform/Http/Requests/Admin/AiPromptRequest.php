<?php
namespace Modules\AiPlatform\Http\Requests\Admin;

final class AiPromptRequest extends AiAdminWriteRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes|required';
        return [
            'key' => ($this->isMethod('POST') ? 'required' : 'sometimes').'|string|max:100',
            'title' => $required.'|string|max:200', 'description' => 'nullable|string|max:10000',
            'capability_key' => 'nullable|string|max:100',
            'template' => $this->isMethod('POST') ? 'required|string|max:100000' : 'nullable|string|max:100000',
        ];
    }
}
