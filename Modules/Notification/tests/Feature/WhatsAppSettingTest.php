<?php

namespace Modules\Notification\tests\Feature;

//   اختبارات التحقق من إدارة حسابات الواتساب المتعددة وعزلها والتحقق من حسابات الإرسال الافتراضية والنشطة واختبار الاتصال.

use App\Models\User;
use App\Models\Company;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Modules\Notification\Models\WhatsAppSetting;

class WhatsAppSettingTest extends TestCase
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
    public function test_can_get_empty_whatsapp_settings()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/v1/whatsapp-settings');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'data' => []
        ]);
    }

    /**
     * اختبار حفظ وتحديث إعدادات الواتساب وتشفير الـ token.
     */
    public function test_can_save_and_update_whatsapp_settings()
    {
        $this->actingAs($this->admin);

        $payload = [
            'title' => 'حساب الواتساب الرئيسي',
            'phone_number_id' => '123456789',
            'waba_id' => '987654321',
            'access_token' => 'EAAGb4ZC3874BAPsecrettoken',
            'is_active' => true,
            'is_default' => false,
        ];

        $response = $this->postJson('/api/v1/whatsapp-settings', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => true,
            'message' => 'تم إضافة حساب الواتساب بنجاح'
        ]);

        $this->assertDatabaseHas('whatsapp_settings', [
            'company_id' => $this->company->id,
            'title' => 'حساب الواتساب الرئيسي',
            'phone_number_id' => '123456789',
            'waba_id' => '987654321',
            'is_default' => true, // الحساب الأول يجب أن يكون افتراضياً تلقائياً
        ]);

        $setting = WhatsAppSetting::where('company_id', $this->company->id)->first();

        // اختبار التعديل عبر PUT دون إرسال التوكن (يجب أن يحافظ على التوكن القديم)
        $updatePayload = [
            'title' => 'حساب الواتساب المحدث',
            'phone_number_id' => '123456789_updated',
            'waba_id' => '987654321',
            'is_active' => true,
            'is_default' => true,
        ];

        $updateResponse = $this->putJson("/api/v1/whatsapp-settings/{$setting->id}", $updatePayload);

        $updateResponse->assertStatus(200);
        $this->assertDatabaseHas('whatsapp_settings', [
            'id' => $setting->id,
            'title' => 'حساب الواتساب المحدث',
            'phone_number_id' => '123456789_updated',
        ]);

        // التأكد من أن التوكن لم يحذف وأنه لا يزال محفوظاً
        $setting->refresh();
        $this->assertEquals('EAAGb4ZC3874BAPsecrettoken', $setting->access_token);
    }

    /**
     * اختبار عدم ظهور رمز الوصول في استجابة الـ API لحماية البيانات الحساسة.
     */
    public function test_api_does_not_expose_whatsapp_access_token()
    {
        $this->actingAs($this->admin);

        $setting = WhatsAppSetting::create([
            'title' => 'حساب سرية التوكن',
            'company_id' => $this->company->id,
            'phone_number_id' => '123456',
            'waba_id' => '654321',
            'access_token' => 'EAAGb4ZC3874BAPsecrettoken',
            'is_active' => true,
            'is_default' => true,
            'created_by' => $this->admin->id
        ]);

        $response = $this->getJson("/api/v1/whatsapp-settings/{$setting->id}");

        $response->assertStatus(200);
        $response->assertJsonMissing(['access_token' => 'EAAGb4ZC3874BAPsecrettoken']);
        $response->assertJsonFragment(['access_token_configured' => true]);
    }

    /**
     * اختبار عزل البيانات ومنع شركة أخرى من جلب أو تعديل إعدادات الواتساب.
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
        $setting = WhatsAppSetting::create([
            'title' => 'حساب شركة 1',
            'company_id' => $this->company->id,
            'phone_number_id' => '123456',
            'waba_id' => '654321',
            'access_token' => 'secret_token_1',
            'created_by' => $this->admin->id
        ]);

        // المحاولة بواسطة شركة 2 لجلب حساب الشركة 1 بالـ ID المباشر
        $this->actingAs($otherAdmin);
        $response = $this->getJson("/api/v1/whatsapp-settings/{$setting->id}");

        // يجب أن يرجع 404 لأن الفلتر التلقائي يعزل الحساب
        $response->assertStatus(404);
    }

    /**
     * اختبار تعيين الحساب الافتراضي وإلغائه تلقائياً من الحسابات الأخرى للواتساب.
     */
    public function test_can_set_default_whatsapp_account()
    {
        $this->actingAs($this->admin);

        // إنشاء حساب 1
        $setting1 = WhatsAppSetting::create([
            'title' => 'الحساب الأول',
            'company_id' => $this->company->id,
            'phone_number_id' => '111',
            'waba_id' => '111',
            'access_token' => 'token1',
            'is_default' => true,
            'is_active' => true,
        ]);

        // إنشاء حساب 2
        $setting2 = WhatsAppSetting::create([
            'title' => 'الحساب الثاني',
            'company_id' => $this->company->id,
            'phone_number_id' => '222',
            'waba_id' => '222',
            'access_token' => 'token2',
            'is_default' => false,
            'is_active' => true,
        ]);

        // إطلاق طلب تعيين الحساب 2 كافتراضي
        $response = $this->postJson("/api/v1/whatsapp-settings/{$setting2->id}/set-default");

        $response->assertStatus(200);

        // التأكد أن الحساب 2 أصبح افتراضياً
        $this->assertEquals(1, WhatsAppSetting::where('id', $setting2->id)->where('is_default', true)->count());
        // التأكد أن الحساب 1 ألغي منه الافتراضي
        $this->assertEquals(0, WhatsAppSetting::where('id', $setting1->id)->where('is_default', true)->count());
    }

    /**
     * اختبار إرسال رسالة واتساب تجريبية (Connection Test).
     */
    public function test_can_send_test_whatsapp_message()
    {
        $this->actingAs($this->admin);

        $setting = WhatsAppSetting::create([
            'title' => 'حساب الاختبار',
            'company_id' => $this->company->id,
            'phone_number_id' => '123456',
            'waba_id' => '654321',
            'access_token' => 'EAAGb4ZC3874BAPsecrettoken',
            'is_active' => true,
            'is_default' => true,
            'created_by' => $this->admin->id
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [
                    ['input' => '201001234567', 'wa_id' => '201001234567']
                ],
                'messages' => [
                    ['id' => 'wamid.HBgLMjAxMDAxMjM0NTY3FQIAERg0QzEzQkUzNTdFMkE1RTdFRDcA']
                ]
            ], 200)
        ]);

        $response = $this->postJson("/api/v1/whatsapp-settings/{$setting->id}/test", [
            'recipient' => '201001234567'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => 'تم إرسال الرسالة التجريبية للواتساب بنجاح، يرجى التحقق من جوال المستلم.'
        ]);
    }
}
