<?php

// تعليق عربي: هجرة لإنشاء جدول إعدادات البريد الإلكتروني الديناميكية لكل شركة (SMTP/Mailgun).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            
            $table->string('mail_transport')->default('smtp'); // smtp, mailgun, ses
            $table->string('mail_host')->nullable();
            $table->integer('mail_port')->nullable();
            $table->string('mail_username')->nullable();
            $table->text('mail_password')->nullable(); // القيمة مشفرة
            $table->string('mail_encryption')->nullable(); // tls, ssl
            $table->string('mail_from_address')->nullable();
            $table->string('mail_from_name')->nullable();

            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->softDeletes();
            $table->timestamps();
            
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
