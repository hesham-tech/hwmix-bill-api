<?php

namespace Modules\Guidance\tests\Feature;

/**
 * كلاس اختبار جودة وعزل موديول الإرشاد والتعليم للمستخدم.
 */

use App\Models\User;
use App\Models\Company;
use App\Models\CompanyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Guidance\Models\UserGuidanceProgress;
use Illuminate\Support\Carbon;

class GuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // إنشاء شركة ومستخدم وتعيينه كنشط
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);

        // ربط المستخدم بالشركة في جدول العلاقة
        CompanyUser::create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'nickname_in_company' => $this->user->username,
            'status' => 'active',
        ]);
    }

    /**
     * اختبار منع الزوار غير المصرح لهم من الوصول إلى روابط الإرشاد.
     */
    public function test_guest_cannot_access_guidance_endpoints(): void
    {
        $this->getJson('/api/v1/guidance/progress')->assertStatus(401);
        $this->postJson('/api/v1/guidance/complete', ['key' => 'test-key'])->assertStatus(401);
        $this->postJson('/api/v1/guidance/reset')->assertStatus(401);
    }

    /**
     * اختبار جلب تقدم الإرشاد الفارغ للمستخدم الجديد.
     */
    public function test_user_can_fetch_empty_guidance_progress(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/guidance/progress');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'data' => []
        ]);
    }

    /**
     * اختبار تسجيل إكمال خطوة إرشادية بنجاح.
     */
    public function test_user_can_complete_guidance_step(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/v1/guidance/complete', [
            'key' => 'dashboard-tour',
            'skipped' => false
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.key', 'dashboard-tour');
        $response->assertJsonPath('data.skipped', false);

        $this->assertDatabaseHas('user_guidance_progress', [
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'key' => 'dashboard-tour',
            'skipped' => false
        ]);
    }

    /**
     * اختبار عزل تقدم الإرشادات بين الشركات المختلفة (Multi-Tenant Isolation).
     */
    public function test_multi_tenant_guidance_isolation(): void
    {
        // إنشاء شركة ومستخدم آخرين
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create([
            'company_id' => $otherCompany->id
        ]);
        CompanyUser::create([
            'user_id' => $otherUser->id,
            'company_id' => $otherCompany->id,
            'nickname_in_company' => $otherUser->username,
            'status' => 'active',
        ]);

        // تسجيل خطوة للمستخدم الأول في الشركة الأولى
        $this->actingAs($this->user);
        $this->postJson('/api/v1/guidance/complete', [
            'key' => 'shared-step-key',
            'skipped' => false
        ])->assertStatus(200);

        // التحقق من أن المستخدم الثاني لا يرى تقدم المستخدم الأول
        $this->actingAs($otherUser);
        $response = $this->getJson('/api/v1/guidance/progress');
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');

        // تسجيل خطوة مختلفة للمستخدم الثاني
        $this->postJson('/api/v1/guidance/complete', [
            'key' => 'shared-step-key',
            'skipped' => true
        ])->assertStatus(200);

        // التحقق من تحديث السجلات بشكل منفصل في قاعدة البيانات
        $this->assertDatabaseHas('user_guidance_progress', [
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'key' => 'shared-step-key',
            'skipped' => false
        ]);

        $this->assertDatabaseHas('user_guidance_progress', [
            'user_id' => $otherUser->id,
            'company_id' => $otherCompany->id,
            'key' => 'shared-step-key',
            'skipped' => true
        ]);
    }

    /**
     * اختبار جودة تكرار العملية (Idempotency) وتحديث السجل بدلاً من التكرار.
     */
    public function test_guidance_step_completion_is_idempotent(): void
    {
        $this->actingAs($this->user);

        // المحاولة الأولى
        $this->postJson('/api/v1/guidance/complete', [
            'key' => 'idempotent-key',
            'skipped' => false
        ])->assertStatus(200);

        // المحاولة الثانية (تحديث نفس الخطوة مع تخطيها)
        $this->postJson('/api/v1/guidance/complete', [
            'key' => 'idempotent-key',
            'skipped' => true
        ])->assertStatus(200);

        // التحقق من وجود سجل واحد فقط وتحديثه بنجاح
        $this->assertEquals(1, UserGuidanceProgress::where('user_id', $this->user->id)
            ->where('company_id', $this->company->id)
            ->where('key', 'idempotent-key')
            ->count());

        $this->assertDatabaseHas('user_guidance_progress', [
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'key' => 'idempotent-key',
            'skipped' => true
        ]);
    }

    /**
     * اختبار إعادة تعيين كافة تقدم إرشادات المستخدم بنجاح.
     */
    public function test_user_can_reset_guidance_progress(): void
    {
        $this->actingAs($this->user);

        // إدخال تقدم أولي
        $this->postJson('/api/v1/guidance/complete', ['key' => 'step-1'])->assertStatus(200);
        $this->postJson('/api/v1/guidance/complete', ['key' => 'step-2'])->assertStatus(200);

        // التأكد من الحفظ في قاعدة البيانات
        $this->assertEquals(2, UserGuidanceProgress::where('user_id', $this->user->id)->count());

        // إعادة الضبط
        $response = $this->postJson('/api/v1/guidance/reset');
        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        // التأكد من الحذف من قاعدة البيانات للمستخدم والشركة المعنيين
        $this->assertEquals(0, UserGuidanceProgress::where('user_id', $this->user->id)->count());
    }

    /**
     * اختبار التراجع عن إكمال خطوة إرشادية بنجاح.
     */
    public function test_user_can_uncomplete_guidance_step(): void
    {
        $this->actingAs($this->user);

        // إكمال الخطوة أولاً
        $this->postJson('/api/v1/guidance/complete', [
            'key' => 'uncomplete-test-step',
        ])->assertStatus(200);

        $this->assertDatabaseHas('user_guidance_progress', [
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'key' => 'uncomplete-test-step',
        ]);

        // إلغاء إكمال الخطوة
        $response = $this->postJson('/api/v1/guidance/uncomplete', [
            'key' => 'uncomplete-test-step',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        // التأكد من الحذف من قاعدة البيانات
        $this->assertDatabaseMissing('user_guidance_progress', [
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'key' => 'uncomplete-test-step',
        ]);
    }
}
