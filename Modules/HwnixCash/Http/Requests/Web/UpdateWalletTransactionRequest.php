<?php
// طلب التحقق من صحة مدخلات تعديل معاملة محفظة إلكترونية.

namespace Modules\HwnixCash\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\HwnixCash\Domain\Enums\WalletOperationType;
use Modules\HwnixCash\Domain\Enums\WalletProvider;
use Modules\HwnixCash\Domain\Enums\WalletTransactionSource;
use Modules\HwnixCash\Domain\Enums\WalletTransactionStatus;

class UpdateWalletTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'line_id' => 'required|integer|exists:hwnix_cash_lines,id',
            'operation_type' => ['required', 'string', new Enum(WalletOperationType::class)],
            'provider' => ['nullable', 'string', new Enum(WalletProvider::class)],
            'status' => ['nullable', 'string', new Enum(WalletTransactionStatus::class)],
            'source' => ['nullable', 'string', new Enum(WalletTransactionSource::class)],
            'amount' => 'required|numeric|min:0',
            'fee' => 'nullable|numeric|min:0',
            'balance_after' => 'nullable|numeric',
            'currency' => 'nullable|string|max:3',
            'operation_number' => 'nullable|string',
            'operation_at' => 'nullable|date',
            'target_phone' => 'nullable|string',
            'target_name' => 'nullable|string',
            'bill_number' => 'nullable|string',
            'raw_sms' => 'required|string',
            'metadata' => 'nullable|array',
        ];
    }
}
