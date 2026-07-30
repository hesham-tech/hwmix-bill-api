<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hwnix_cash_financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('line_id')->constrained('hwnix_cash_lines')->onDelete('cascade');
            $table->foreignId('message_source_id')->constrained('hwnix_cash_message_sources')->onDelete('cascade');
            $table->string('name');
            $table->string('account_number')->nullable();
            $table->decimal('balance', 18, 2)->default(0.00);
            $table->decimal('actual_balance', 18, 2)->default(0.00);
            $table->decimal('daily_withdraw_limit', 18, 2)->nullable();
            $table->decimal('daily_deposit_limit', 18, 2)->nullable();
            $table->decimal('monthly_withdraw_limit', 18, 2)->nullable();
            $table->decimal('monthly_deposit_limit', 18, 2)->nullable();
            $table->string('status', 20)->default('active');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // إضافة financial_account_id لجدول المعاملات المالية
        if (Schema::hasTable('hwnix_cash_wallet_transactions')) {
            Schema::table('hwnix_cash_wallet_transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('hwnix_cash_wallet_transactions', 'financial_account_id')) {
                    $table->foreignId('financial_account_id')->nullable()->after('created_by')->constrained('hwnix_cash_financial_accounts')->onDelete('cascade');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hwnix_cash_wallet_transactions')) {
            Schema::table('hwnix_cash_wallet_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('hwnix_cash_wallet_transactions', 'financial_account_id')) {
                    $table->dropForeign(['financial_account_id']);
                    $table->dropColumn('financial_account_id');
                }
            });
        }

        Schema::dropIfExists('hwnix_cash_financial_accounts');
    }
};
