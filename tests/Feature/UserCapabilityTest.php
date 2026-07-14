<?php

namespace Tests\Feature;

// ملف اختبارات شامل للتحقق من قدرات وسلوكيات الأطراف والعلاقات التجارية وتأمين النواة المالية.

use App\Models\User;
use App\Models\Company;
use Modules\Companies\Models\RelationType;
use Modules\Companies\Models\Capability;
use Modules\Companies\Models\BusinessRelation;
use Modules\Accounting\Models\CashBox;
use App\Services\CashBoxService;
use Database\Seeders\RelationCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // تفعيل قيود المفتاح الخارجي في SQLite للاختبارات
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        // زرع صلاحيات النظام الأساسية
        $this->seed(\Database\Seeders\AddPermissionsSeeder::class);

        // زرع العلاقات والقدرات الأساسية
        $this->seed(RelationCapabilitiesSeeder::class);

        $this->company = Company::factory()->create();
        
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->givePermissionTo('admin.super');

        // ربط المدير بالشركة لتجنب أخطاء تصفية المستأجرين
        \App\Models\CompanyUser::create([
            'user_id' => $this->admin->id,
            'company_id' => $this->company->id,
        ]);

        // زرع نوع الخزنة الافتراضي لتمكين عمليات الإنشاء في الخدمة
        \App\Models\CashBoxType::create([
            'name' => 'نقدي',
            'company_id' => $this->company->id,
        ]);
    }

    /**
     * 1) التحقق من دمج القدرات (OR / Union) لمستخدم متعدد العلاقات (موظف + عميل)
     */
    public function test_user_with_multi_relations_has_merged_capabilities()
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);

        $employeeType = RelationType::where('code', 'employee')->first();
        $customerType = RelationType::where('code', 'customer')->first();

        // إضافة علاقة موظف
        BusinessRelation::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'relation_type' => 'employee',
            'relation_type_id' => $employeeType->id,
            'is_active' => true,
        ]);

        // إضافة علاقة عميل
        BusinessRelation::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'relation_type' => 'customer',
            'relation_type_id' => $customerType->id,
            'is_active' => true,
        ]);

        // يجب أن يملك قدرات الموظف والعميل معاً (دمج OR)
        $this->assertTrue($user->hasCapability('has_cash_custody', $this->company->id));
        $this->assertTrue($user->hasCapability('track_receivable', $this->company->id));
        $this->assertFalse($user->hasCapability('track_payable', $this->company->id)); // ليس لديه علاقة مورد
    }

    /**
     * 2) التحقق من منع إنشاء خزنة نقدية لمستخدم لا يملك قدرة عهدة نقدية
     */
    public function test_cash_box_creation_blocks_when_user_lacks_capability()
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $customerType = RelationType::where('code', 'customer')->first();

        // عميل فقط (لا يملك عهدة نقدية)
        BusinessRelation::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'relation_type' => 'customer',
            'relation_type_id' => $customerType->id,
            'is_active' => true,
        ]);

        $cashBox = app(CashBoxService::class)->createDefaultCashBoxForUserCompany(
            $user->id,
            $this->company->id,
            $this->admin->id
        );

        // يجب أن ترفض الخدمة وتُرجع null
        $this->assertNull($cashBox);
        $this->assertDatabaseMissing('cash_boxes', [
            'user_id' => $user->id,
            'company_id' => $this->company->id,
        ]);
    }

    /**
     * 3) التحقق من السماح بإنشاء خزنة نقدية لمستخدم يملك قدرة عهدة نقدية (موظف)
     */
    public function test_cash_box_creation_allowed_when_user_has_capability()
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $employeeType = RelationType::where('code', 'employee')->first();

        // موظف
        BusinessRelation::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'relation_type' => 'employee',
            'relation_type_id' => $employeeType->id,
            'is_active' => true,
        ]);

        $cashBox = app(CashBoxService::class)->createDefaultCashBoxForUserCompany(
            $user->id,
            $this->company->id,
            $this->admin->id
        );

        // يجب أن تنجح الخدمة وتُرجع الخزنة
        $this->assertNotNull($cashBox);
        $this->assertDatabaseHas('cash_boxes', [
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'is_default' => 1,
        ]);
    }

    /**
     * 4) التحقق من تعارضات القدرات غير المنطقية (عدم السماح بعهد نقدية لأطراف خارجية)
     */
    public function test_validate_capabilities_consistency_throws_on_non_internal_custody()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('تعارض: لا يمكن تمكين قدرة العهدة النقدية');

        // محاولة إعطاء عهدة نقدية دون وسم مستخدم داخلي
        RelationType::validateCapabilitiesConsistency([
            'has_cash_custody',
            // لا يوجد is_internal
        ]);
    }

    /**
     * 5) التحقق من تعارضات القدرات غير المنطقية (عدم السماح بـ دائن ومدين معاً لنفس نوع العلاقة)
     */
    public function test_validate_capabilities_consistency_throws_on_receivable_and_payable()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('تعارض: لا يمكن لنوع علاقة واحد تتبع الذمم المدينة والدائنة معاً');

        // محاولة تتبع مدين ودائن في نفس الوقت
        RelationType::validateCapabilitiesConsistency([
            'track_receivable',
            'track_payable',
        ]);
    }

    /**
     * 6) التحقق من تعديل القدرات للمستخدم ديناميكياً عند تغيير نوع العلاقة
     */
    public function test_user_capabilities_update_when_relation_type_changes()
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $employeeType = RelationType::where('code', 'employee')->first();
        $customerType = RelationType::where('code', 'customer')->first();

        // البدء كـ عميل
        $relation = BusinessRelation::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'relation_type' => 'customer',
            'relation_type_id' => $customerType->id,
            'is_active' => true,
        ]);

        $this->assertTrue($user->hasCapability('track_receivable', $this->company->id));
        $this->assertFalse($user->hasCapability('has_cash_custody', $this->company->id));

        // تغيير العلاقة إلى موظف
        $relation->update([
            'relation_type' => 'employee',
            'relation_type_id' => $employeeType->id,
        ]);

        // مسح الكاش المؤقت للتحقق الفعلي
        $user->unsetRelation('businessRelations');
        // تفريغ كاش القدرات في السمة
        $refObject = new \ReflectionObject($user);
        $refProperty = $refObject->getProperty('resolvedCapabilities');
        $refProperty->setAccessible(true);
        $refProperty->setValue($user, []);

        $this->assertFalse($user->hasCapability('track_receivable', $this->company->id));
        $this->assertTrue($user->hasCapability('has_cash_custody', $this->company->id));
    }

    /**
     * 7) التحقق من حجب القدرات عند تعطيل العلاقة التجارية
     */
    public function test_capabilities_blocked_when_relation_is_disabled()
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $employeeType = RelationType::where('code', 'employee')->first();

        $relation = BusinessRelation::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'relation_type' => 'employee',
            'relation_type_id' => $employeeType->id,
            'is_active' => true,
        ]);

        $this->assertTrue($user->hasCapability('has_cash_custody', $this->company->id));

        // تعطيل العلاقة
        $relation->update(['is_active' => false]);

        $user->unsetRelation('businessRelations');
        $refObject = new \ReflectionObject($user);
        $refProperty = $refObject->getProperty('resolvedCapabilities');
        $refProperty->setAccessible(true);
        $refProperty->setValue($user, []);

        // يجب ألا تحسب القدرة له حالياً
        $this->assertFalse($user->hasCapability('has_cash_custody', $this->company->id));
    }

    /**
     * 8) التحقق من أن حذف نوع العلاقة ينجح ويحذف سجل نوع العلاقة من قاعدة البيانات
     */
    public function test_deleting_relation_type_in_use_cascades_deletions()
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $employeeType = RelationType::where('code', 'employee')->first();

        $relation = BusinessRelation::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'relation_type' => 'employee',
            'relation_type_id' => $employeeType->id,
            'is_active' => true,
        ]);

        // نقوم بحذف نوع العلاقة
        $employeeType->delete();

        // يجب أن يتم حذف نوع العلاقة بنجاح من جدول الأنواع
        $this->assertDatabaseMissing('relation_types', [
            'id' => $employeeType->id,
        ]);
    }

    /**
     * 9) اختبار دمجي كامل (Integration Test) لدورة حياة علاقات وقدرات المستخدم بالكامل
     */
    public function test_complete_user_capability_and_provisioning_lifecycle()
    {
        // 1. إنشاء نوع علاقة مخصص جديد (مثال: شريك موزع partner-distributor)
        $partnerDistributorType = RelationType::create([
            'code' => 'partner-distributor',
            'display_name' => 'شريك موزع',
        ]);

        // 2. ربطه بالقدرات (له عهدة نقدية ولديه ذمم مدينة للعملاء)
        $custodyCap = Capability::where('code', 'has_cash_custody')->first();
        $receivableCap = Capability::where('code', 'track_receivable')->first();
        $isInternalCap = Capability::where('code', 'is_internal')->first();

        $partnerDistributorType->capabilities()->attach([
            $custodyCap->id,
            $receivableCap->id,
            $isInternalCap->id // عهد نقدية تستلزم وسم مستخدم داخلي
        ]);

        // 3. إنشاء مستخدم جديد
        $user = User::factory()->create(['company_id' => $this->company->id]);

        // 4. ربطه بالعلاقة التجارية
        BusinessRelation::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'relation_type' => 'partner-distributor',
            'relation_type_id' => $partnerDistributorType->id,
            'is_active' => true,
        ]);

        // 5. محاكاة ربط الشركة (CompanyUser) والذي يطلق Observer الإنشاء
        $companyUser = \App\Models\CompanyUser::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
        ]);

        // 6. التحقق من التجهيز التلقائي للعهد والذمم المحاسبية (Provisioning)
        // أ) يجب إنشاء خزنة نقدية تلقائياً لوجود قدرة has_cash_custody
        $this->assertDatabaseHas('cash_boxes', [
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);

        // ب) يجب إنشاء سجل ذمم مالية تلقائياً لوجود قدرة track_receivable
        $this->assertDatabaseHas('stakeholder_financial_balances', [
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'relation_type' => 'receivable',
        ]);

        // 7. التحقق من استجابة الـ API والتوافق
        $this->actingAs($this->admin);
        $response = $this->getJson("/api/v1/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.capabilities', [
                'has_cash_custody',
                'track_receivable',
                'is_internal'
            ]);
    }
}
