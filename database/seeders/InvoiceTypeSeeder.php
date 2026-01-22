<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InvoiceType;
use App\Models\Company;
use App\Models\User;

class InvoiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        $user = User::first();

        if (!$company || !$user) {
            return;
        }

        $types = [
            [
                'name' => 'فاتورة بيع',
                'description' => 'فاتورة بيع للعميل، مع تحديث المخزون تلقائياً.',
                'code' => 'sale',
                'context' => 'sales',
                'company_id' => $company->id,
                'created_by' => $user->id,
            ],
            [
                'name' => 'فاتورة شراء',
                'description' => 'فاتورة شراء من المورد، مع تحديث المخزون تلقائياً.',
                'code' => 'purchase',
                'context' => 'purchases',
                'company_id' => $company->id,
                'created_by' => $user->id,
            ],
            [
                'name' => 'سند قبض',
                'description' => 'سند استلام مبالغ نقدية.',
                'code' => 'receipt',
                'context' => 'finance',
                'company_id' => $company->id,
                'created_by' => $user->id,
            ],
            [
                'name' => 'سند صرف',
                'description' => 'سند صرف مبالغ نقدية.',
                'code' => 'payment',
                'context' => 'finance',
                'company_id' => $company->id,
                'created_by' => $user->id,
            ],
        ];

        foreach ($types as $type) {
            InvoiceType::firstOrCreate(['code' => $type['code'], 'company_id' => $type['company_id']], $type);
        }
    }
}
