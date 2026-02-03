<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
                $table->string('subject_type')->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('action', 50);
                $table->text('description');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('url')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['subject_type', 'subject_id']);
                $table->index('user_id');
                $table->index('company_id');
                $table->index('action');
                $table->index('created_at');
            });
        } else {
            // في حال وجود الجدول، نتأكد من إضافة الحقول الناقصة (تحقيقاً لطلب المستخدم)
            Schema::table('activity_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('activity_logs', 'old_values')) {
                    $table->json('old_values')->nullable()->after('description');
                }
                if (!Schema::hasColumn('activity_logs', 'new_values')) {
                    $table->json('new_values')->nullable()->after('old_values');
                }
                if (!Schema::hasColumn('activity_logs', 'ip_address')) {
                    $table->string('ip_address', 45)->nullable()->after('new_values');
                }
                if (!Schema::hasColumn('activity_logs', 'user_agent')) {
                    $table->text('user_agent')->nullable()->after('ip_address');
                }
                if (!Schema::hasColumn('activity_logs', 'metadata')) {
                    $table->json('metadata')->nullable()->after('url');
                }
                // يمكن إضافة باقي الحقول بنفس الطريقة إذا استدعى الأمر
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
