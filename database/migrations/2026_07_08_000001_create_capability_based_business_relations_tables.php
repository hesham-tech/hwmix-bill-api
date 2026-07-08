<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. جدول أنواع العلاقات التجارية (relation_types)
        Schema::create('relation_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // employee, customer, supplier, driver, partner...
            $table->string('display_name');
            $table->timestamps();
        });

        // 2. جدول القدرات والسلوكيات التشغيلية (capabilities)
        Schema::create('capabilities', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // has_cash_custody, track_receivable, track_payable, is_internal...
            $table->string('display_name');
            $table->timestamps();
        });

        // 3. جدول الربط (relation_type_capabilities)
        Schema::create('relation_type_capabilities', function (Blueprint $table) {
            $table->foreignId('relation_type_id')->constrained('relation_types')->onDelete('cascade');
            $table->foreignId('capability_id')->constrained('capabilities')->onDelete('cascade');
            $table->primary(['relation_type_id', 'capability_id'], 'rel_type_cap_primary');
        });

        // 4. تعديل جدول business_relations لإضافة المعرف الجديد
        Schema::table('business_relations', function (Blueprint $table) {
            $table->foreignId('relation_type_id')->nullable()->after('relation_type')->constrained('relation_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_relations', function (Blueprint $table) {
            $table->dropForeign(['relation_type_id']);
            $table->dropColumn('relation_type_id');
        });

        Schema::dropIfExists('relation_type_capabilities');
        Schema::dropIfExists('capabilities');
        Schema::dropIfExists('relation_types');
    }
};
