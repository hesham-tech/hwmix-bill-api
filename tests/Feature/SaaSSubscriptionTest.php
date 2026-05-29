<?php

namespace Tests\Feature;

// تعليق عربي: اختبارات التحقق من قيود ساس واشتراكات الشركات والتسجيل بالشركة الأم والتحكم في التجديد التلقائي.

use App\Models\User;
use App\Models\Company;
use App\Models\Plan;
use App\Models\CompanySubscription;
use App\Models\CompanyUser;
use App\Services\SaaS\LimitResolver;
use App\Services\SaaS\FeatureAccessService;
use Database\Seeders\AddPermissionsSeeder;
use Database\Seeders\SaaSDefaultPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaaSSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // تشغيل الصلاحيات والسيدر للباقة الافتراضية
        $this->seed(AddPermissionsSeeder::class);
        $this->seed(SaaSDefaultPlanSeeder::class);

        // إنشاء الشركة الأم أو تحديثها
        $masterCompanyId = (int) config('app.master_company_id', 1);
        Company::updateOrCreate(
            ['id' => $masterCompanyId],
            [
                'name' => 'الشركة الأم لنظام الساس',
                'description' => 'الشركة الرئيسية لنظام SaaS لإدارة الحسابات العامة',
                'field' => 'system',
                'owner_name' => 'Admin'
            ]
        );
    }

    /** @test */
    public function test_provision_company_initializes_master_company_membership_and_free_trial()
    {
        $payload = [
            'company_name' => 'شركة هيباتيا للبرمجيات',
            'full_name' => 'أحمد هيبة',
            'phone' => '01012345678',
            'password' => 'password123',
            'email' => 'ahmed@hypatia.com',
            'address' => 'القاهرة، مصر',
        ];

        $response = $this->postJson('/api/v1/register/company', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['status', 'data' => ['company', 'user', 'token']]);

        $this->assertDatabaseHas('companies', [
            'name' => 'شركة هيباتيا للبرمجيات'
        ]);

        $newCompany = Company::where('name', 'شركة هيباتيا للبرمجيات')->first();
        $newUser = User::where('phone', '01012345678')->first();

        // التحقق من أنه تم ربطه بالشركة الجديدة
        $this->assertDatabaseHas('company_user', [
            'user_id' => $newUser->id,
            'company_id' => $newCompany->id,
            'status' => 'active'
        ]);

        // التحقق من أنه تم ربطه بالشركة الأم كعضو بسيط
        $masterCompanyId = (int) config('app.master_company_id', 1);
        $this->assertDatabaseHas('company_user', [
            'user_id' => $newUser->id,
            'company_id' => $masterCompanyId,
            'status' => 'active'
        ]);

        // التحقق من تفعيل الباقة التجريبية المجانية تلقائياً
        $this->assertDatabaseHas('company_subscriptions', [
            'company_id' => $newCompany->id,
            'status' => 'trial',
            'price' => 0.00
        ]);
    }

    /** @test */
    public function test_can_retrieve_my_subscription_usage_matrix()
    {
        // 1. تسجيل مستأجر جديد
        $payload = [
            'company_name' => 'شركة النور',
            'full_name' => 'نور الدين',
            'phone' => '01012345679',
            'password' => 'password123',
        ];
        $this->postJson('/api/v1/register/company', $payload);

        $newUser = User::where('phone', '01012345679')->first();
        $newCompany = Company::where('name', 'شركة النور')->first();

        $this->actingAs($newUser);

        $response = $this->getJson('/api/v1/saas/my-subscription');

        $response->assertStatus(200)
            ->assertJsonPath('data.plan_name', 'الباقة التجريبية المجانية')
            ->assertJsonPath('data.status', 'trial')
            ->assertJsonStructure([
                'status',
                'data' => [
                    'plan_name',
                    'status',
                    'auto_renew',
                    'limits' => [
                        'users' => ['current', 'max', 'percent'],
                        'products' => ['current', 'max', 'percent'],
                        'invoices' => ['current', 'max', 'percent'],
                        'warehouses' => ['current', 'max', 'percent'],
                    ]
                ]
            ]);
    }

    /** @test */
    public function test_can_toggle_subscription_auto_renew()
    {
        // 1. تسجيل مستأجر جديد
        $payload = [
            'company_name' => 'شركة الفتح',
            'full_name' => 'عبد الفتاح',
            'phone' => '01012345670',
            'password' => 'password123',
        ];
        $this->postJson('/api/v1/register/company', $payload);

        $newUser = User::where('phone', '01012345670')->first();
        $newCompany = Company::where('name', 'شركة الفتح')->first();

        $this->actingAs($newUser);

        // التجديد التلقائي الافتراضي يكون true
        $this->assertDatabaseHas('company_subscriptions', [
            'company_id' => $newCompany->id,
            'auto_renew' => true
        ]);

        // 2. إيقاف التجديد التلقائي
        $response = $this->patchJson('/api/v1/saas/my-subscription/toggle-auto-renew', [
            'auto_renew' => false
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.auto_renew', false);

        $this->assertDatabaseHas('company_subscriptions', [
            'company_id' => $newCompany->id,
            'auto_renew' => false
        ]);

        // 3. إعادة تفعيل التجديد التلقائي
        $response = $this->patchJson('/api/v1/saas/my-subscription/toggle-auto-renew', [
            'auto_renew' => true
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.auto_renew', true);

        $this->assertDatabaseHas('company_subscriptions', [
            'company_id' => $newCompany->id,
            'auto_renew' => true
        ]);
    }

    /** @test */
    public function test_limits_and_features_check()
    {
        // 1. تسجيل مستأجر جديد
        $payload = [
            'company_name' => 'شركة التقنية',
            'full_name' => 'مصطفى التقني',
            'phone' => '01012345671',
            'password' => 'password123',
        ];
        $this->postJson('/api/v1/register/company', $payload);

        $newUser = User::where('phone', '01012345671')->first();
        $newCompany = Company::where('name', 'شركة التقنية')->first();

        // فحص ميزات الباقة التجريبية الافتراضية
        // الباقة التجريبية المجانية تملك mail_settings = true ولكن payment_gateways = false
        $this->assertTrue(FeatureAccessService::hasAccess($newCompany->id, 'mail_settings'));
        $this->assertFalse(FeatureAccessService::hasAccess($newCompany->id, 'payment_gateways'));

        // فحص الحدود
        // الباقة التجريبية تدعم 3 مستخدمين (بما فيهم المالك الحالي = 1 مستخدم)
        $this->assertTrue(LimitResolver::isWithinLimit($newCompany->id, 'users'));
    }

    /** @test */
    public function test_can_upgrade_subscription_plan()
    {
        // 1. تسجيل مستأجر جديد
        $payload = [
            'company_name' => 'شركة هورايزون',
            'full_name' => 'طارق هورايزون',
            'phone' => '01012345672',
            'password' => 'password123',
        ];
        $this->postJson('/api/v1/register/company', $payload);

        $newUser = User::where('phone', '01012345672')->first();
        $newCompany = Company::where('name', 'شركة هورايزون')->first();

        // 2. إنشاء باقة جديدة مميزة (Active Premium Plan) للترقية إليها
        $masterCompanyId = (int) config('app.master_company_id', 1);
        $premiumPlan = Plan::create([
            'name' => 'الباقة المميزة للشبكات',
            'code' => 'premium_networks',
            'price' => 250.00,
            'currency' => 'EGP',
            'duration' => 1,
            'duration_unit' => 'months',
            'trial_days' => 0,
            'is_active' => true,
            'company_id' => $masterCompanyId,
            'features' => [
                'payment_gateways' => true,
                'installment_system' => true,
                'mail_settings' => true
            ],
            'max_users' => 10,
            'max_products' => 100,
            'max_invoices' => 1000,
        ]);

        $this->actingAs($newUser);

        // 3. محاولة الترقية للباقة المميزة
        $response = $this->postJson('/api/v1/saas/my-subscription/upgrade', [
            'plan_id' => $premiumPlan->id
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.plan_name', 'الباقة المميزة للشبكات')
            ->assertJsonPath('data.status', 'active');

        // التحقق من قاعدة البيانات
        $this->assertDatabaseHas('company_subscriptions', [
            'company_id' => $newCompany->id,
            'plan_id' => $premiumPlan->id,
            'status' => 'active',
            'price' => 250.00
        ]);

        // التحقق من الميزات الجديدة للشركة
        $this->assertTrue(FeatureAccessService::hasAccess($newCompany->id, 'payment_gateways'));
        $this->assertTrue(FeatureAccessService::hasAccess($newCompany->id, 'installment_system'));
    }
}

