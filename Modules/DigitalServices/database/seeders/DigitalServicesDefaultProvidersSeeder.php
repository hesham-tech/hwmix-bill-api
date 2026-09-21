<?php

namespace Modules\DigitalServices\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\HwnixCash\Domain\Enums\WalletProvider;

class DigitalServicesDefaultProvidersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $providers = [
            // Wallets (محافظ)
            [
                'name' => 'فودافون كاش',
                'code' => 'VODAFONE_CASH',
                'category' => 'wallet',
                'is_active' => true,
            ],
            [
                'name' => 'أورانج كاش',
                'code' => 'ORANGE_CASH',
                'category' => 'wallet',
                'is_active' => true,
            ],
            [
                'name' => 'اتصالات كاش',
                'code' => 'ETISALAT_CASH',
                'category' => 'wallet',
                'is_active' => true,
            ],
            [
                'name' => 'وي كاش (We Pay)',
                'code' => 'WE_PAY',
                'category' => 'wallet',
                'is_active' => true,
            ],
            [
                'name' => 'إنستاباي (InstaPay)',
                'code' => 'INSTAPAY',
                'category' => 'wallet',
                'is_active' => true,
            ],
            
            // Machines (ماكينات دفع)
            [
                'name' => 'فوري (Fawry)',
                'code' => 'FAWRY',
                'category' => 'machine',
                'is_active' => true,
            ],
            [
                'name' => 'أمان (Aman)',
                'code' => 'AMAN',
                'category' => 'machine',
                'is_active' => true,
            ],
            [
                'name' => 'مصاري (Masary)',
                'code' => 'MASARY',
                'category' => 'machine',
                'is_active' => true,
            ],
        ];

        foreach ($providers as $provider) {
            // Use updateOrInsert to prevent duplicates if ran multiple times
            DB::table('service_providers')->updateOrInsert(
                ['code' => $provider['code'], 'company_id' => null],
                [
                    'name' => $provider['name'],
                    'category' => $provider['category'],
                    'is_active' => $provider['is_active'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
        
        // TODO: Later we can also seed default 'service_definitions' (like Cash In, Cash Out)
        // using the IDs of these newly inserted providers.
    }
}
