<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RepairCompanyUserDataSeeder extends Seeder
{
    public function run(): void
    {
        $companyUsers = DB::table('company_user')->get();
        $count = 0;

        foreach ($companyUsers as $cu) {
            $user = DB::table('users')->find($cu->user_id);
            if (!$user)
                continue;

            $update = [];

            if (empty($cu->nickname_in_company)) {
                $update['nickname_in_company'] = $user->nickname ?? $user->username;
            }

            if (empty($cu->full_name_in_company)) {
                $update['full_name_in_company'] = $user->full_name;
            }

            if (empty($cu->status)) {
                $update['status'] = 'active';
            }

            if (!empty($update)) {
                DB::table('company_user')->where('id', $cu->id)->update($update);
                $count++;
            }
        }

        $this->command->info("Repaired {$count} company_user records.");
    }
}
