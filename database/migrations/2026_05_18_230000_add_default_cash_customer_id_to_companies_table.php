<?php

/**
 * هجرة قاعدة البيانات لإضافة حقل default_cash_customer_id لجدول الشركات
 * وتجهيز عميل نقدي افتراضي فريد لكل شركة موجودة مسبقاً في النظام.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. إضافة العمود لجدول الشركات بشكل شرطي لتجنب أخطاء التكرار
        if (!Schema::hasColumn('companies', 'default_cash_customer_id')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->unsignedBigInteger('default_cash_customer_id')->nullable()->after('created_by');
                $table->foreign('default_cash_customer_id')->references('id')->on('users')->onDelete('set null');
            });
        }

        // 2. تجهيز العملاء الافتراضيين للشركات الحالية
        $companies = DB::table('companies')->get();

        foreach ($companies as $company) {
            $phone = '999' . str_pad($company->id, 7, '0', STR_PAD_LEFT);
            $email = "cash.customer.{$company->id}@hwnix.local";
            $username = "cash_customer_{$company->id}";

            // فحص وجود المستخدم مسبقاً
            $userId = DB::table('users')->where('phone', $phone)->value('id');

            if (!$userId) {
                $userId = DB::table('users')->insertGetId([
                    'phone' => $phone,
                    'email' => $email,
                    'full_name' => 'عميل نقدي',
                    'nickname' => 'عميل نقدي',
                    'password' => Hash::make('cash_customer_secret'),
                    'username' => $username,
                    'created_by' => $company->created_by ?? 1,
                    'active_company_id' => $company->id,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // ربط المستخدم بالشركة في جدول company_user إن لم يكن مرتبطاً
            $pivotExists = DB::table('company_user')
                ->where('user_id', $userId)
                ->where('company_id', $company->id)
                ->exists();

            if (!$pivotExists) {
                DB::table('company_user')->insert([
                    'user_id' => $userId,
                    'company_id' => $company->id,
                    'nickname_in_company' => 'عميل نقدي',
                    'full_name_in_company' => 'عميل نقدي',
                    'customer_type_in_company' => 'cash_customer',
                    'status' => 'active',
                    'created_by' => $company->created_by ?? 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // ربط العميل بالفرع الرئيسي للشركة
            $defaultBranchId = DB::table('branches')
                ->where('company_id', $company->id)
                ->where('is_default', true)
                ->value('id');

            if ($defaultBranchId) {
                $branchUserExists = DB::table('branch_user')
                    ->where('user_id', $userId)
                    ->where('branch_id', $defaultBranchId)
                    ->exists();

                if (!$branchUserExists) {
                    DB::table('branch_user')->insert([
                        'user_id' => $userId,
                        'branch_id' => $defaultBranchId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // تحديث الشركة بمعرف العميل الافتراضي الجديد
            DB::table('companies')
                ->where('id', $company->id)
                ->update(['default_cash_customer_id' => $userId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                if (Schema::hasColumn('companies', 'default_cash_customer_id')) {
                    $table->dropForeign(['default_cash_customer_id']);
                }
            }
            if (Schema::hasColumn('companies', 'default_cash_customer_id')) {
                $table->dropColumn('default_cash_customer_id');
            }
        });
    }
};
