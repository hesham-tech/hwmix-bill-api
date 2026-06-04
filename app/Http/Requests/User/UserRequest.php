<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = \Illuminate\Support\Facades\Auth::user()->active_company_id ?? $this->input('company_id');

        return [
            'phone' => [
                'required',
                'string',
                'max:15',
                function ($attribute, $value, $fail) use ($companyId) {
                    $existingUser = \App\Models\User::withoutGlobalScopes()
                        ->where('phone', $value)
                        ->first();

                    if ($existingUser && $companyId) {
                        $isLinked = \App\Models\CompanyUser::where('user_id', $existingUser->id)
                            ->where('company_id', $companyId)
                            ->exists();

                        if ($isLinked) {
                            $fail('رقم الهاتف مسجل بالفعل ومرتبط بهذه الشركة.');
                        }
                    }
                }
            ],
            'password' => 'nullable|string|min:8',
            'email' => [
                'nullable',
                'email',
                function ($attribute, $value, $fail) {
                    $phone = $this->input('phone');
                    $existingUserByPhone = \App\Models\User::withoutGlobalScopes()
                        ->where('phone', $phone)
                        ->first();

                    $existingUserByEmail = \App\Models\User::withoutGlobalScopes()
                        ->where('email', $value)
                        ->first();

                    if ($existingUserByEmail) {
                        if (!$existingUserByPhone || $existingUserByEmail->id !== $existingUserByPhone->id) {
                            $fail('البريد الإلكتروني مسجل بالفعل لمستخدم آخر.');
                        }
                    }
                }
            ],
            'full_name' => 'required|string|max:255',
            'nickname' => 'required|string|max:255',
            'username' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    $phone = $this->input('phone');
                    $existingUserByPhone = \App\Models\User::withoutGlobalScopes()
                        ->where('phone', $phone)
                        ->first();

                    if (!$existingUserByPhone) {
                        $usernameExists = \App\Models\User::withoutGlobalScopes()
                            ->where('username', $value)
                            ->exists();

                        if ($usernameExists) {
                            $fail('اسم المستخدم مسجل بالفعل في النظام.');
                        }
                    } else {
                        $existingUserByUsername = \App\Models\User::withoutGlobalScopes()
                            ->where('username', $value)
                            ->first();

                        if ($existingUserByUsername && $existingUserByUsername->id !== $existingUserByPhone->id) {
                            $fail('اسم المستخدم مسجل بالفعل لمستخدم آخر.');
                        }
                    }
                }
            ],
            'position' => 'nullable|string|max:255',
            'settings' => 'nullable|array',
            'last_login_at' => 'nullable|date',
            'email_verified_at' => 'nullable|date',
            'type' => 'nullable|in:system_owner,company_owner,sales,accounting,client,user',
            'balance' => 'nullable|numeric',
            'images_ids' => 'nullable|array',
            'status' => 'nullable|in:active,inactive',
            'company_id' => 'nullable|exists:companies,id',
            'company_ids' => 'nullable|array',
            'company_ids.*' => 'nullable|exists:companies,id',
            'created_by' => 'nullable|exists:users,id',
            'customer_type' => 'nullable|in:retail,wholesale',
            'roles' => 'nullable|array',
            'roles.*' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
        ];
    }
}
