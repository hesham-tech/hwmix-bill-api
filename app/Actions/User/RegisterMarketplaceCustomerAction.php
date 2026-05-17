<?php

/**
 * كلاس مسؤول عن تسجيل عملاء المتجر (Marketplace)
 * ينشئ هوية عالمية فقط دون ربطها بأي شركة في البداية (Lazy ERP)
 */

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class RegisterMarketplaceCustomerAction
{
    /**
     * تنفيذ عملية التسجيل
     *
     * @param array $data بيانات العميل
     * @return User
     * @throws Throwable
     */
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data) {
            // 1. إنشاء المستخدم العالمي
            $user = User::create([
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'full_name' => $data['full_name'],
                'nickname' => $data['nickname'] ?? $data['full_name'],
                'password' => Hash::make($data['password']),
                'username' => $data['username'] ?? $data['phone'],
            ]);

            return $user;
        });
    }
}
