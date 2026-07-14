<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\CashBox;
use Modules\Companies\Models\Capability;
use Modules\Companies\Models\BusinessRelation;
use App\Models\CompanyUser;
use Modules\Companies\Models\RelationType;
use App\Enums\CashBoxStatus;
use App\Services\CashBoxLifecycleService;
use App\Services\CashBoxAccessService;
use App\Services\DefaultCashBoxResolver;
use App\Services\FinancialEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Exception;

class CashBoxAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected $company;
    protected $branch;
    protected $cashBoxType;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\AddPermissionsSeeder::class);
        $this->seed(\Database\Seeders\RelationCapabilitiesSeeder::class);

        $this->company = Company::factory()->create();
        $this->branch = \Modules\Companies\Models\Branch::create([
            'company_id' => $this->company->id,
            'name' => 'الفرع الرئيسي',
            'is_default' => true,
            'is_active' => true,
        ]);

        // زرع نوع الخزنة الافتراضي باسم "نقدي" المطلوب لخدمة التزويد
        $this->cashBoxType = \App\Models\CashBoxType::create([
            'company_id' => $this->company->id,
            'name' => 'نقدي',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'active_company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    /**
     * السيناريو الأول: دورة حياة الموظف
     */
    public function test_scenario_one_employee_lifecycle()
    {
        $this->actingAs($this->admin);

        // 1. إنشاء موظف جديد يمتلك has_cash_custody
        $employee = User::factory()->create([
            'company_id' => $this->company->id,
            'active_company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);

        $employeeRelationType = RelationType::where('code', 'employee')->firstOrFail();

        BusinessRelation::create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'relation_type' => 'employee',
            'relation_type_id' => $employeeRelationType->id,
            'is_active' => true,
        ]);

        // إطلاق تزويد الخزنة عبر CompanyUser
        $companyUser = CompanyUser::create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
        ]);

        // 2. التأكد من إنشاء خزنة تلقائياً
        $box = CashBox::where('user_id', $employee->id)
            ->where('company_id', $this->company->id)
            ->first();
        $this->assertNotNull($box);
        $this->assertEquals(CashBoxStatus::ACTIVE, $box->status);

        // 3. تعيينها كخزنته الافتراضية
        $lifecycleService = app(CashBoxLifecycleService::class);
        $lifecycleService->changeDefault($employee, $box->id);
        $this->assertEquals($box->id, $employee->fresh()->default_cash_box_id);

        // 4. إجراء إيداع
        $engine = app(FinancialEngine::class);
        $engine->receiveMoney(1000.00, $box->id, 'op-123', ['user_id' => $employee->id]);
        $this->assertEquals(1000.00, $box->fresh()->balance);

        // 5. سحب مبلغ
        $engine->payMoney(300.00, $box->id, 'op-124', ['user_id' => $employee->id]);
        $this->assertEquals(700.00, $box->fresh()->balance);

        // 6. تعطيل الخزنة (يحاكي تعطيل الموظف أو فك ارتباطه)
        $lifecycleService->deactivate($box);

        // 7. التأكد من تعطيل الخزنة وعدم إمكانية استخدامها
        $this->assertEquals(CashBoxStatus::INACTIVE, $box->fresh()->status);
        
        $this->expectException(Exception::class);
        $engine->receiveMoney(100.00, $box->id, 'op-125', ['user_id' => $employee->id]);
    }

    /**
     * السيناريو الثاني: الخزن المشتركة
     */
    public function test_scenario_two_shared_cash_boxes()
    {
        $this->actingAs($this->admin);

        $lifecycleService = app(CashBoxLifecycleService::class);
        $accessService = app(CashBoxAccessService::class);

        // 1. إنشاء موظفين
        $employee1 = User::factory()->create([
            'company_id' => $this->company->id,
            'active_company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);
        $employee2 = User::factory()->create([
            'company_id' => $this->company->id,
            'active_company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);

        // 2. إنشاء خزنة مشتركة
        $sharedBox = $lifecycleService->create([
            'name' => 'الخزينة الرئيسية المشتركة',
            'cash_box_type_id' => $this->cashBoxType->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'access_type' => 'company_shared',
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        // 3. منح الوصول لموظف 1 وموظف 2
        $lifecycleService->grantAccess($sharedBox, $employee1->id);
        $lifecycleService->grantAccess($sharedBox, $employee2->id);

        // 4. التأكد أن الموظفين المصرح لهم فقط يستطيعون الوصول
        $this->actingAs($employee1);
        $this->assertTrue($accessService->canAccess($employee1, $sharedBox));
        
        $this->actingAs($employee2);
        $this->assertTrue($accessService->canAccess($employee2, $sharedBox));

        // موظف خارجي عشوائي لا يمكنه الوصول
        $randomUser = User::factory()->create([
            'company_id' => $this->company->id,
            'active_company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->actingAs($randomUser);
        $this->assertFalse($accessService->canAccess($randomUser, $sharedBox));

        // 5. إزالة صلاحية أحدهم (موظف 2)
        $this->actingAs($this->admin);
        $lifecycleService->revokeAccess($sharedBox, $employee2->id);

        // 6. التأكد من رفض الوصول بعد إزالة الصلاحية
        $this->actingAs($employee1);
        $this->assertTrue($accessService->canAccess($employee1, $sharedBox));
        
        $this->actingAs($employee2);
        $this->assertFalse($accessService->canAccess($employee2, $sharedBox));
    }

    /**
     * السيناريو الثالث: تعدد الخزن وتغيير الافتراضية
     */
    public function test_scenario_three_multiple_cash_boxes()
    {
        $this->actingAs($this->admin);

        $lifecycleService = app(CashBoxLifecycleService::class);
        $resolver = app(DefaultCashBoxResolver::class);

        $employee = User::factory()->create([
            'company_id' => $this->company->id,
            'active_company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);

        $employeeRelationType = RelationType::where('code', 'employee')->firstOrFail();

        BusinessRelation::create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'relation_type' => 'employee',
            'relation_type_id' => $employeeRelationType->id,
            'is_active' => true,
        ]);

        // 1. إنشاء ثلاث خزائن لنفس المستخدم
        $box1 = $lifecycleService->create([
            'name' => 'Safe 1',
            'cash_box_type_id' => $this->cashBoxType->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'user_id' => $employee->id,
            'access_type' => 'personal',
            'created_by' => $this->admin->id,
        ]);
        $box2 = $lifecycleService->create([
            'name' => 'Safe 2',
            'cash_box_type_id' => $this->cashBoxType->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'user_id' => $employee->id,
            'access_type' => 'personal',
            'created_by' => $this->admin->id,
        ]);
        $box3 = $lifecycleService->create([
            'name' => 'Safe 3',
            'cash_box_type_id' => $this->cashBoxType->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'user_id' => $employee->id,
            'access_type' => 'personal',
            'created_by' => $this->admin->id,
        ]);

        // 2. تغيير الخزنة الافتراضية أكثر من مرة
        $lifecycleService->changeDefault($employee, $box1->id);
        $this->assertEquals($box1->id, $resolver->resolve($employee, $this->company->id)->id);

        $lifecycleService->changeDefault($employee, $box2->id);
        $this->assertEquals($box2->id, $resolver->resolve($employee, $this->company->id)->id);

        $lifecycleService->changeDefault($employee, $box3->id);
        $this->assertEquals($box3->id, $resolver->resolve($employee, $this->company->id)->id);

        // 3. التأكد أن تغيير الافتراضية لا يؤثر على أرصدة أي خزنة
        $this->assertEquals(0.00, $box1->fresh()->balance);
        $this->assertEquals(0.00, $box2->fresh()->balance);
        $this->assertEquals(0.00, $box3->fresh()->balance);
    }

    /**
     * السيناريو الرابع: العزل بين الشركات والفروع
     */
    public function test_scenario_four_isolation()
    {
        $this->actingAs($this->admin);

        $lifecycleService = app(CashBoxLifecycleService::class);
        $accessService = app(CashBoxAccessService::class);

        // 1. إنشاء شركتين وفروع لكل منهما
        $company1 = $this->company;
        $company2 = Company::factory()->create();

        $branch2 = \Modules\Companies\Models\Branch::create([
            'company_id' => $company2->id,
            'name' => 'فرع الشركة الثانية',
            'is_default' => true,
            'is_active' => true,
        ]);

        $employee1 = User::factory()->create([
            'company_id' => $company1->id,
            'active_company_id' => $company1->id,
            'branch_id' => $this->branch->id,
        ]);
        $employee2 = User::factory()->create([
            'company_id' => $company2->id,
            'active_company_id' => $company2->id,
            'branch_id' => $branch2->id,
        ]);

        // خزنة في الشركة الأولى
        $boxCompany1 = $lifecycleService->create([
            'name' => 'Safe Company 1',
            'cash_box_type_id' => $this->cashBoxType->id,
            'company_id' => $company1->id,
            'branch_id' => $this->branch->id,
            'access_type' => 'company_shared',
            'created_by' => $this->admin->id,
        ]);

        // خزنة في الشركة الثانية
        $boxCompany2 = $lifecycleService->create([
            'name' => 'Safe Company 2',
            'cash_box_type_id' => $this->cashBoxType->id,
            'company_id' => $company2->id,
            'branch_id' => $branch2->id,
            'access_type' => 'company_shared',
            'created_by' => $this->admin->id,
        ]);

        // 2. التأكد من عزل الوصول
        $lifecycleService->grantAccess($boxCompany1, $employee1->id);
        $lifecycleService->grantAccess($boxCompany2, $employee2->id);

        $this->actingAs($employee1);
        $this->assertTrue($accessService->canAccess($employee1, $boxCompany1));
        $this->assertFalse($accessService->canAccess($employee1, $boxCompany2));

        $this->actingAs($employee2);
        $this->assertTrue($accessService->canAccess($employee2, $boxCompany2));
        $this->assertFalse($accessService->canAccess($employee2, $boxCompany1));
    }

    /**
     * السيناريو الخامس: حماية الرصيد من التعديل المباشر
     */
    public function test_scenario_five_balance_protection()
    {
        $this->actingAs($this->admin);

        $lifecycleService = app(CashBoxLifecycleService::class);
        $box = $lifecycleService->create([
            'name' => 'Safe Test Protection',
            'cash_box_type_id' => $this->cashBoxType->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'access_type' => 'company_shared',
            'created_by' => $this->admin->id,
        ]);

        // 1. محاولة تعديل الرصيد مباشرة
        $this->expectException(Exception::class);
        $box->balance = 500.00;
        $box->save();
    }

    /**
     * السيناريو السادس: سلامة البيانات (Health Check)
     */
    public function test_scenario_six_health_check()
    {
        // تشغيل الأمر والتأكد من سلامة قاعدة البيانات
        $exitCode = Artisan::call('cashboxes:health-check');
        $this->assertEquals(0, $exitCode);
    }
}
