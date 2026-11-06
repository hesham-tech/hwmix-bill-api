<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('mail_settings',function(Blueprint $table) {
            $table->string('mail_reply_to_address')->nullable();
            $table->string('mail_reply_to_name')->nullable();
        });
    }
    public function down(): void {
        Schema::table('mail_settings',fn(Blueprint $table)=>$table->dropColumn(['mail_reply_to_address','mail_reply_to_name']));
    }
};
