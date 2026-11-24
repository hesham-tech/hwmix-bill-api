<?php
namespace Modules\AiPlatform\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

abstract class AiAdminWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->checkPermissionTo(perm_key('admin.super'));
    }

    protected function failedValidation(Validator $validator): void
    {
        // Do not pass API key payloads to the shared response/error logger.
        throw new HttpResponseException(new JsonResponse(['success' => false, 'message' => 'بيانات الإدارة غير صالحة.', 'errors' => $validator->errors()], 422));
    }
}
