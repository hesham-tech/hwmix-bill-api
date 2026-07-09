<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Modules\Accounting\Models\CashBox;
use Illuminate\Support\Facades\Auth;

/**
 * قاعدة تحقق AccessibleCashBox
 * التحقق من صلاحية وصول المستخدم النشط إلى الخزينة المحددة في النظام المالي.
 */
class AccessibleCashBox implements Rule
{
    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value): bool
    {
        if (empty($value)) {
            return true; // nullable cases are handled by request validation rules
        }

        $authUser = Auth::user();
        if (!$authUser) {
            return false;
        }

        $cashBox = CashBox::withoutGlobalScopes()->find($value);
        if (!$cashBox) {
            return false;
        }

        return $authUser->canAccessCashBox($cashBox);
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'ليس لديك صلاحية الوصول إلى الخزينة المحددة أو أنها غير صالحة.';
    }
}
