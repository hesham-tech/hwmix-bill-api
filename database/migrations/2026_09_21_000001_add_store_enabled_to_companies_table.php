<?php
// إضافة حقل store_enabled لجدول الشركات لتحديد ما إذا كانت الشركة مؤهلة للنشر في المتجر
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('store_enabled')->default(false)->after('settings');
            $table->index('store_enabled');
        });
    }
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['store_enabled']);
            $table->dropColumn('store_enabled');
        });
    }
};
