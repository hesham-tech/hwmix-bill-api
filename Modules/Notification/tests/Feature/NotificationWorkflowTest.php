<?php

namespace Modules\Notification\tests\Feature;

//   اختبارات التحقق من إدارة قوالب الإشعارات وقواعد الأتمتة المجدولة وعزل الشركات والتشغيل الفوري والتلقائي للفحوصات.

use App\Models\User;
use App\Models\Company;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Models\NotificationWorkflow;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\InvoiceType;
use Carbon\Carbon;

class NotificationWorkflowTest extends TestCase
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

        // تهيئة إعدادات البريد النشطة للشركة لتمرير التحقق
        \Modules\Notification\Models\MailSetting::create([
            'company_id' => $this->company->id,
            'mail_transport' => 'smtp',
            'mail_host' => 'smtp.mailtrap.io',
            'mail_port' => 2525,
            'mail_username' => 'test',
            'mail_password' => 'test',
            'mail_from_address' => 'system@test.com',
            'mail_from_name' => 'System Test',
            'is_active' => true,
            'is_default' => true,
        ]);

        // تهيئة إعدادات الواتساب النشطة للشركة لتمرير التحقق
        \Modules\Notification\Models\WhatsAppSetting::create([
            'company_id' => $this->company->id,
            'title' => 'حساب واتساب تجريبي',
            'phone_number_id' => '123456789',
            'waba_id' => '987654321',
            'access_token' => 'test_token',
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    /**
     * اختبار إدارة قوالب الرسائل (إضافة وتعديل وحذف).
     */
    public function test_can_manage_notification_templates()
    {
        $this->actingAs($this->admin);

        $payload = [
            'name' => 'قالب تذكير السداد',
            'channel' => 'both',
            'subject' => 'تنبيه دفع متأخر',
            'body' => 'مرحباً {customer_name}، نود تذكيركم بسداد مبلغ {invoice_amount} لمستند {invoice_number}.',
            'is_active' => true,
        ];

        // 1. إضافة قالب جديد
        $response = $this->postJson('/api/v1/notification-templates', $payload);
        $response->assertStatus(201);
        $this->assertDatabaseHas('notification_templates', [
            'company_id' => $this->company->id,
            'name' => 'قالب تذكير السداد',
        ]);

        $template = NotificationTemplate::first();

        // 2. تعديل القالب
        $updatePayload = array_merge($payload, ['name' => 'قالب تذكير سداد معدل']);
        $updateResponse = $this->putJson("/api/v1/notification-templates/{$template->id}", $updatePayload);
        $updateResponse->assertStatus(200);
        $this->assertDatabaseHas('notification_templates', [
            'id' => $template->id,
            'name' => 'قالب تذكير سداد معدل',
        ]);

        // 3. حذف القالب
        $deleteResponse = $this->deleteJson("/api/v1/notification-templates/{$template->id}");
        $deleteResponse->assertStatus(200);
        $this->assertSoftDeleted('notification_templates', ['id' => $template->id]);
    }

    /**
     * اختبار إدارة قواعد أتمتة الإشعارات والخطوات (Workflows & Steps).
     */
    public function test_can_manage_notification_workflows()
    {
        $this->actingAs($this->admin);

        // إنشاء قالب للإسناد
        $template = NotificationTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'قالب اختبار الخطوات',
            'channel' => 'whatsapp',
            'body' => 'مرحباً بالعميل',
        ]);

        $payload = [
            'event_type' => 'invoice.overdue',
            'is_active' => true,
            'steps' => [
                [
                    'step_number' => 1,
                    'delay_days' => 5,
                    'channel' => 'whatsapp',
                    'template_id' => $template->id,
                    'is_active' => true,
                ]
            ]
        ];

        // 1. إضافة قاعدة جديدة بـ 1 خطوة
        $response = $this->postJson('/api/v1/notification-workflows', $payload);
        $response->assertStatus(201);

        $this->assertDatabaseHas('notification_workflows', [
            'company_id' => $this->company->id,
            'event_type' => 'invoice.overdue',
        ]);

        $workflow = NotificationWorkflow::first();

        $this->assertDatabaseHas('notification_workflow_steps', [
            'workflow_id' => $workflow->id,
            'delay_days' => 5,
            'template_id' => $template->id,
        ]);

        // 2. تحديث القاعدة وإضافة خطوة ثانية وتعديل الخطوة الأولى
        $firstStep = $workflow->steps->first();

        $updatePayload = [
            'event_type' => 'invoice.overdue',
            'is_active' => true,
            'steps' => [
                [
                    'id' => $firstStep->id,
                    'step_number' => 1,
                    'delay_days' => 2, // تعديل الإزاحة
                    'channel' => 'whatsapp',
                    'template_id' => $template->id,
                    'is_active' => true,
                ],
                [
                    'step_number' => 2,
                    'delay_days' => 7, // خطوة جديدة
                    'channel' => 'email',
                    'template_id' => $template->id,
                    'is_active' => true,
                ]
            ]
        ];

        $updateResponse = $this->putJson("/api/v1/notification-workflows/{$workflow->id}", $updatePayload);
        $updateResponse->assertStatus(200);

        $this->assertDatabaseHas('notification_workflow_steps', [
            'id' => $firstStep->id,
            'delay_days' => 2,
        ]);

        $this->assertDatabaseHas('notification_workflow_steps', [
            'workflow_id' => $workflow->id,
            'delay_days' => 7,
            'channel' => json_encode(['email']),
        ]);
    }

    /**
     * اختبار عزل بيانات الأتمتة ومنع التداخل بين الشركات (Multi-Tenant Isolation).
     */
    public function test_company_data_isolation()
    {
        $otherCompany = Company::factory()->create();
        $otherAdmin = User::factory()->create([
            'company_id' => $otherCompany->id,
            'active_company_id' => $otherCompany->id,
        ]);
        $otherAdmin->givePermissionTo('admin.super');

        // إنشاء قالب لشركة 1
        $template = NotificationTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'قالب شركة 1',
            'channel' => 'email',
            'body' => 'مرحباً',
        ]);

        // المحاولة بواسطة شركة 2 لجلب قالب الشركة 1
        $this->actingAs($otherAdmin);
        $response = $this->getJson("/api/v1/notification-templates/{$template->id}");

        // يجب أن يرجع 404 بسبب عزل البيانات Scoped By Active Company ID
        $response->assertStatus(404);
    }

    /**
     * اختبار تشغيل الفحص يدوياً وتطبيق الأتمتة لإرسال الإشعار للفاتورة المتأخرة.
     */
    public function test_run_now_triggers_notifications_for_overdue_invoices()
    {
        $this->actingAs($this->admin);

        // 1. إنشاء قالب إشعار نشط
        $template = NotificationTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'تنبيه الفاتورة المتأخرة',
            'channel' => 'email',
            'subject' => 'تنبيه متأخرات الفاتورة {invoice_number}',
            'body' => 'مرحباً {customer_name}، نود تنبيهك بسداد مبلغ {invoice_amount}.',
            'is_active' => true,
        ]);

        // 2. إنشاء قاعدة وخطوة بـ إزاحة 5 أيام (بعد الاستحقاق)
        $workflow = NotificationWorkflow::create([
            'company_id' => $this->company->id,
            'event_type' => 'invoice.overdue',
            'is_active' => true,
        ]);

        $step = $workflow->steps()->create([
            'step_number' => 1,
            'delay_days' => 5,
            'channel' => 'email',
            'template_id' => $template->id,
            'is_active' => true,
        ]);

        // 3. إنشاء عميل وفاتورة تستحق منذ 5 أيام وتكون غير مدفوعة
        $customer = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'client@overduetest.com',
            'nickname' => 'أحمد العميل',
        ]);

        $invoiceType = InvoiceType::create([
            'name' => 'فاتورة مبيعات',
            'code' => 'sale',
            'context' => 'sales',
        ]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'invoice_type_id' => $invoiceType->id,
            'invoice_number' => 'INV-TEST-101',
            'user_id' => $customer->id,
            'gross_amount' => 1500.00,
            'net_amount' => 1500.00,
            'paid_amount' => 0,
            'remaining_amount' => 1500.00,
            'payment_status' => 'unpaid',
            'status' => 'confirmed',
            'due_date' => Carbon::today()->subDays(5)->toDateString(),
            'issue_date' => Carbon::today()->subMonths(1)->toDateString(),
        ]);

        Mail::fake();

        // 4. إطلاق طلب تشغيل الأتمتة يدوياً الآن
        $response = $this->postJson("/api/v1/notification-workflows/{$workflow->id}/run");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'processed_count' => 1
        ]);
    }
}
