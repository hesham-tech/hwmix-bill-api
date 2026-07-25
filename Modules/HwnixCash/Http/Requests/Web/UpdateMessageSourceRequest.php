<?php
// طلب التحقق من صحة مدخلات تعديل مصدر رسائل معتمد.

namespace Modules\HwnixCash\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\HwnixCash\Domain\Enums\WalletProvider;

class UpdateMessageSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sender_identifier' => 'required|string|max:191',
            'provider' => ['nullable', 'string', new Enum(WalletProvider::class)],
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ];
    }
}
