<?php

//   هجرة لإنشاء جدول تتبع وظائف الاستيراد والتصدير بالخلفية لكل شركة وفرع.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('export_import_jobs', function (Blueprint $table) {
            $table->id();

            $table->string('type'); // export, import
            $table->string('model_type'); // Products, Invoices, Customers
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->integer('progress')->default(0); // نسبة الإنجاز 0 - 100
            $table->text('file_path')->nullable(); // مسار تخزين الملف الناتج أو المرفوع
            $table->json('errors')->nullable(); // سجلات الأخطاء والأسطر الفاشلة إن وجدت

            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'branch_id']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_import_jobs');
    }
};
