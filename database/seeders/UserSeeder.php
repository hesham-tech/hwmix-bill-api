<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'phone' => '01006444992',
                'email' => 'retail@example.com',
                'full_name' => 'محمد أحمد القحطاني',
                'nickname' => 'محمد القحطاني',
                'username' => 'retail_customer',
                'password' => Hash::make('12345678'),
                'created_by' => 1,
                'customer_type' => 'retail',
            ],
            [
                'phone' => '01006444993',
                'email' => 'wholesale@example.com',
                'full_name' => 'شركة الأمل للتوزيع',
                'nickname' => 'الأمل للتوزيع',
                'username' => 'wholesale_customer',
                'password' => Hash::make('12345678'),
                'created_by' => 1,
                'customer_type' => 'wholesale',
            ],
        ];

        $companyIds = Company::pluck('id')->toArray();

        foreach ($users as $userData) {
            $customerType = $userData['customer_type'];
            unset($userData['customer_type']);

            $user = User::create($userData);
            $pivotData = [];
            foreach ($companyIds as $companyId) {
                $pivotData[$companyId] = [
                    'created_by' => $userData['created_by'],
                    'nickname_in_company' => $user->nickname,
                    'full_name_in_company' => $user->full_name,
                    'customer_type_in_company' => $customerType,
                    'status' => 'active',
                ];
            }
            $user->companies()->sync($pivotData);
        }
    }
}
