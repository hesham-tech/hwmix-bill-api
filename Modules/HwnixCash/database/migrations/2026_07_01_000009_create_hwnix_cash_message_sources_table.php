<?php
// ترحيل لإنشاء جدول مصادر الرسائل المعتمدة في كاش هونكس HwnixCash.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hwnix_cash_message_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->string('sender_identifier')->index();
            $table->string('provider')->default('other')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hwnix_cash_message_sources');
    }
};
