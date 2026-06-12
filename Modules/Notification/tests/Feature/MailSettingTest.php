<?php

namespace Modules\Notification\tests\Feature;

//   اختبارات التحقق من إدارة حسابات البريد الإلكتروني المتعددة وعزلها والتحقق من حسابات الإرسال الافتراضية والنشطة.

use App\Models\User;
use App\Models\Company;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Modules\Notification\Models\MailSetting;

class MailSettingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);
        $this->company = Company::factory()->create();

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'active_company_id' => $this->company->id,
        ]);

        $this->admin->givePermissionTo('admin.super');
    }

    /**
     * اختبار جلب الإعدادات عندما تكون فارغة لأول مرة.
     */
    public function test_can_get_empty_mail_settings()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/v1/mail-settings');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'data' => []
        ]);
    }

    /**
     * اختبار حفظ وتحديث إعدادات البريد الإلكتروني وتشفير كلمة السر.
     */
    public function test_can_save_and_update_mail_settings()
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin);

        $payload = [
            'title' => 'خادم البريد الأساسي',
            'mail_transport' => 'smtp',
            'mail_host' => 'smtp.mailtrap.io',
            'mail_port' => 2525,
            'mail_username' => 'test_user',
            'mail_password' => 'secret_password_123',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'noreply@hwnix-test.com',
            'mail_from_name' => 'HWNix Test',
        ];

        $response = $this->postJson('/api/v1/mail-settings', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => true,
            'message' => 'تم إضافة حساب البريد بنجاح'
        ]);

        $this->assertDatabaseHas('mail_settings', [
            'company_id' => $this->company->id,
            'title' => 'خادم البريد الأساسي',
            'mail_host' => 'smtp.mailtrap.io',
            'mail_username' => 'test_user',
            'is_default' => true, // الحساب الأول يجب أن يكون افتراضياً تلقائياً
        ]);

        $setting = MailSetting::where('company_id', $this->company->id)->first();

        // اختبار التعديل عبر PUT
        $updatePayload = array_merge($payload, [
            'title' => 'خادم البريد الرئيسي المحدث',
            'mail_password' => 'new_secret_password'
        ]);

        $updateResponse = $this->putJson("/api/v1/mail-settings/{$setting->id}", $updatePayload);

        $updateResponse->assertStatus(200);
        $this->assertDatabaseHas('mail_settings', [
            'id' => $setting->id,
            'title' => 'خادم البريد الرئيسي المحدث',
        ]);
    }

    /**
     * اختبار عدم ظهور كلمة السر في استجابة الـ API لحماية البيانات الحساسة.
     */
    public function test_api_does_not_expose_mail_password()
    {
        $this->actingAs($this->admin);

        $setting = MailSetting::create([
            'title' => 'حساب اختبار سرية كلمة المرور',
            'company_id' => $this->company->id,
            'mail_transport' => 'smtp',
            'mail_host' => 'smtp.mailtrap.io',
            'mail_port' => 2525,
            'mail_username' => 'test_user',
            'mail_password' => 'secret_password_123',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'noreply@hwnix-test.com',
            'mail_from_name' => 'HWNix Test',
            'created_by' => $this->admin->id
        ]);

        $response = $this->getJson("/api/v1/mail-settings/{$setting->id}");

        $response->assertStatus(200);
        $response->assertJsonMissing(['mail_password' => 'secret_password_123']);
        $response->assertJsonFragment(['mail_password_configured' => true]);
    }

    /**
     * اختبار عزل البيانات ومنع شركة أخرى من جلب أو تعديل إعدادات البريد.
     */
    public function test_company_data_isolation()
    {
        $otherCompany = Company::factory()->create();
        $otherAdmin = User::factory()->create([
            'company_id' => $otherCompany->id,
            'active_company_id' => $otherCompany->id,
        ]);
        $otherAdmin->givePermissionTo('admin.super');

        // حفظ إعدادات لشركة 1
        $setting = MailSetting::create([
            'title' => 'حساب شركة 1',
            'company_id' => $this->company->id,
            'mail_transport' => 'smtp',
            'mail_host' => 'smtp.company1.com',
            'mail_from_address' => 'noreply@company1.com',
            'mail_from_name' => 'Company One',
            'created_by' => $this->admin->id
        ]);

        // المحاولة بواسطة شركة 2 لجلب حساب الشركة 1 بالـ ID المباشر
        $this->actingAs($otherAdmin);
        $response = $this->getJson("/api/v1/mail-settings/{$setting->id}");

        // يجب أن يرجع 404 لأن الفلتر التلقائي scoped by active_company_id يعزل الحساب
        $response->assertStatus(404);
    }

    /**
     * اختبار تعيين الحساب الافتراضي وإلغائه تلقائياً من الحسابات الأخرى.
     */
    public function test_can_set_default_mail_account()
    {
        $this->actingAs($this->admin);

        // إنشاء خادم بريد 1 (افتراضي افتراضياً لأنه الأول)
        $setting1 = MailSetting::create([
            'title' => 'الخادم الأول',
            'company_id' => $this->company->id,
            'mail_transport' => 'smtp',
            'mail_from_address' => 'server1@test.com',
            'mail_from_name' => 'Server 1',
            'is_default' => true,
            'is_active' => true,
        ]);

        // إنشاء خادم بريد 2
        $setting2 = MailSetting::create([
            'title' => 'الخادم الثاني',
            'company_id' => $this->company->id,
            'mail_transport' => 'smtp',
            'mail_from_address' => 'server2@test.com',
            'mail_from_name' => 'Server 2',
            'is_default' => false,
            'is_active' => true,
        ]);

        // إطلاق طلب تعيين الخادم 2 كافتراضي
        $response = $this->postJson("/api/v1/mail-settings/{$setting2->id}/set-default");

        $response->assertStatus(200);

        // التأكد أن الخادم 2 أصبح افتراضياً
        $this->assertEquals(1, MailSetting::where('id', $setting2->id)->where('is_default', true)->count());
        // التأكد أن الخادم 1 ألغي منه الافتراضي
        $this->assertEquals(0, MailSetting::where('id', $setting1->id)->where('is_default', true)->count());
    }

    /**
     * اختبار إرسال البريد التجريبي (Connection Test).
     */
    public function test_can_send_test_email()
    {
        $this->actingAs($this->admin);

        $setting = MailSetting::create([
            'title' => 'حساب الاختبار',
            'company_id' => $this->company->id,
            'mail_transport' => 'smtp',
            'mail_host' => 'smtp.mailtrap.io',
            'mail_port' => 2525,
            'mail_username' => 'test_user',
            'mail_password' => 'secret_password_123',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'noreply@hwnix-test.com',
            'mail_from_name' => 'HWNix Test',
            'created_by' => $this->admin->id
        ]);

        Mail::fake();

        $response = $this->postJson("/api/v1/mail-settings/{$setting->id}/test", [
            'recipient' => 'client@hwnix-test.com'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => 'تم إرسال البريد التجريبي بنجاح، يرجى التحقق من صندوق الوارد.'
        ]);
    }
}
