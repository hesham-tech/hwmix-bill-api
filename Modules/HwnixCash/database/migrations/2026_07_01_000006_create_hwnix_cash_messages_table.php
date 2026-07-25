<?php
// إنشاء جدول سجلات الرسائل القصيرة لكاش هونكس.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hwnix_cash_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('created_by')->index();

            $table->foreignId('sms_device_id')
                ->nullable()
                ->constrained('hwnix_cash_devices')
                ->onDelete('set null');

            $table->foreignId('sms_line_id')
                ->nullable()
                ->constrained('hwnix_cash_lines')
                ->onDelete('set null');

            $table->string('phone_number', 50)->index();
            $table->text('message_body');
            $table->string('direction', 20)->default('outgoing')->index();
            $table->string('status', 30)->default('queued')->index();
            $table->string('message_ref', 100)->nullable();
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['sms_device_id', 'message_ref', 'direction'], 'hwnix_cash_idempotency_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hwnix_cash_messages');
    }
};
