<?php
// ترحيل لإنشاء جدول معاملات المحافظ الإلكترونية الخاصة بكاش هونكس.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hwnix_cash_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('line_id')->constrained('hwnix_cash_lines')->onDelete('cascade');
            
            $table->string('operation_type')->index();
            $table->string('provider')->default('other')->index();
            $table->string('status')->default('success')->index();
            $table->string('source')->default('sms')->index();

            $table->decimal('amount', 18, 2)->default(0.00);
            $table->decimal('fee', 18, 2)->default(0.00);
            $table->decimal('balance_after', 18, 2)->nullable();
            $table->string('currency', 3)->default('EGP');

            $table->string('operation_number')->nullable()->index();
            $table->timestamp('operation_at')->nullable()->index();

            $table->string('target_phone')->nullable();
            $table->string('target_name')->nullable();
            $table->string('bill_number')->nullable();

            $table->text('raw_sms');
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hwnix_cash_wallet_transactions');
    }
};
