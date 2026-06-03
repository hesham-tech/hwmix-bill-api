<?php

// تعليق عربي: هجرة لإنشاء جداول أتمتة الإشعارات وقوالب الرسائل وخطوات الجدولة لكل شركة بشكل معزول وآمن.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('channel'); // whatsapp, email, both
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('notification_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('event_type'); // e.g. invoice.created, invoice.overdue, stock.low
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'event_type'], 'comp_wf_event_uniq');
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('notification_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('notification_workflows')->onDelete('cascade');
            $table->integer('step_number');
            $table->integer('delay_days')->default(0); // 0 = فوري، سالب = قبل الموعد، موجب = بعد الموعد
            $table->string('channel'); // whatsapp, email, both
            $table->foreignId('template_id')->constrained('notification_templates')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['workflow_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_workflow_steps');
        Schema::dropIfExists('notification_workflows');
        Schema::dropIfExists('notification_templates');
    }
};
