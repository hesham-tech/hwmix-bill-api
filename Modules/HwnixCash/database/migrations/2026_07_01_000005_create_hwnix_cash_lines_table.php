<?php
// إنشاء جدول شرائح وخطوط اتصال كاش هونكس.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hwnix_cash_lines', function (Blueprint $table) {
            $table->id();
            $table->string('device_android_id', 100)->index();
            $table->foreign('device_android_id')
                ->references('android_id')
                ->on('hwnix_cash_devices')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('created_by')->index();

            $table->integer('slot_index')->default(0);
            $table->string('subscription_id', 100)->nullable();
            $table->string('carrier', 100)->nullable();
            $table->string('phone_number', 50);
            $table->decimal('balance', 18, 2)->default(0.00);
            $table->decimal('actual_balance', 18, 2)->default(0.00);
            $table->integer('daily_limit')->default(0);
            $table->string('status', 30)->default('active')->index();
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hwnix_cash_lines');
    }
};
