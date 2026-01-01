<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateCompanyUserWithUserDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // تحديث الصفوف الموجودة في جدول company_user
        DB::table('company_user')
            ->get() // جلب جميع الصفوف لتحديثها
            ->each(function ($companyUser) {
                // ابحث عن المستخدم الأساسي المرتبط بهذا السجل
                $user = DB::table('users')->find($companyUser->user_id);

                if ($user) {
                    // تحديث الحقول الجديدة ببيانات المستخدم الأساسية
                    DB::table('company_user')
                        ->where('id', $companyUser->id)
                        ->update([
                            'nickname_in_company' => $user->nickname,
                            'full_name_in_company' => $user->full_name ?? null,
                            'position_in_company' => $user->position ?? null,
                            'user_phone' => $user->phone,
                            'user_email' => $user->email,
                            'user_username' => $user->username,
                        ]);
                }
            });
    }
}
