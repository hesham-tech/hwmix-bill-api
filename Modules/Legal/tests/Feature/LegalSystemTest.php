<?php

namespace Modules\Legal\tests\Feature;

// تعليق عربي لقواعد الكلاسات: اختبارات التحقق من إدارة المستندات القانونية وإصداراتها وموافقات المستخدمين وعزل الشركات وسجلات الأنشطة.

use App\Models\User;
use App\Models\Company;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Legal\Models\LegalDocument;
use Modules\Legal\Models\LegalDocumentVersion;
use Modules\Legal\Models\LegalDocumentAcceptance;
use App\Models\ActivityLog;

class LegalSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $tenantAdminA;
    protected User $tenantAdminB;
    protected Company $companyA;
    protected Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed permissions
        $this->seed(AddPermissionsSeeder::class);

        // Create companies
        $this->companyA = Company::factory()->create();
        $this->companyB = Company::factory()->create();

        // Create super admin
        $this->superAdmin = User::factory()->create([
            'company_id' => $this->companyA->id,
            'active_company_id' => $this->companyA->id,
        ]);
        setPermissionsTeamId($this->companyA->id);
        $this->superAdmin->givePermissionTo('admin.super');

        // Create tenant admins
        $this->tenantAdminA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'active_company_id' => $this->companyA->id,
        ]);
        setPermissionsTeamId($this->companyA->id);
        $this->tenantAdminA->givePermissionTo(perm_key('legal_documents.view_all'));
        $this->tenantAdminA->givePermissionTo(perm_key('legal_documents.create'));
        $this->tenantAdminA->givePermissionTo(perm_key('legal_documents.update_all'));
        $this->tenantAdminA->givePermissionTo(perm_key('legal_documents.delete_all'));

        $this->tenantAdminB = User::factory()->create([
            'company_id' => $this->companyB->id,
            'active_company_id' => $this->companyB->id,
        ]);
        setPermissionsTeamId($this->companyB->id);
        $this->tenantAdminB->givePermissionTo(perm_key('legal_documents.view_all'));
        $this->tenantAdminB->givePermissionTo(perm_key('legal_documents.create'));
        $this->tenantAdminB->givePermissionTo(perm_key('legal_documents.update_all'));
        $this->tenantAdminB->givePermissionTo(perm_key('legal_documents.delete_all'));

        // Reset Spatie team ID to Company A by default for standard requests
        setPermissionsTeamId($this->companyA->id);
    }

    /**
     * اختبار إنشاء مستند قانوني جديد (مسودة) والتحقق من الصلاحيات.
     */
    public function test_can_create_legal_document()
    {
        $this->actingAs($this->tenantAdminA);

        $payload = [
            'key' => 'privacy-policy',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/legal/admin/documents', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => true,
            'message' => 'تم إنشاء المستند القانوني بنجاح.'
        ]);

        $this->assertDatabaseHas('legal_documents', [
            'key' => 'privacy-policy',
            'company_id' => $this->companyA->id,
        ]);
    }

    /**
     * اختبار منع تكرار مفتاح المستند لنفس الشركة.
     */
    public function test_cannot_duplicate_document_key_for_same_company()
    {
        $this->actingAs($this->tenantAdminA);

        LegalDocument::create([
            'key' => 'privacy-policy',
            'company_id' => $this->companyA->id,
            'is_active' => true,
        ]);

        $payload = [
            'key' => 'privacy-policy',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/legal/admin/documents', $payload);

        $response->assertStatus(422);
    }

    /**
     * اختبار إنشاء مسودة إصدار جديد لمستند قانوني.
     */
    public function test_can_create_draft_version()
    {
        $this->actingAs($this->tenantAdminA);

        $document = LegalDocument::create([
            'key' => 'terms-of-use',
            'company_id' => $this->companyA->id,
            'is_active' => true,
        ]);

        $payload = [
            'version' => '1.0',
            'title' => 'شروط الاستخدام v1.0',
            'content' => 'محتوى تجريبي لشروط الاستخدام',
            'summary' => 'الإصدار الأول لشروط الاستخدام الخاصة بالمنصة',
        ];

        $response = $this->postJson("/api/v1/legal/admin/documents/{$document->id}/versions", $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('legal_document_versions', [
            'version' => '1.0',
            'title' => 'شروط الاستخدام v1.0',
            'status' => 'draft',
            'legal_document_id' => $document->id,
        ]);
    }

    /**
     * اختبار نشر إصدار جديد وأرشفة الإصدارات السابقة.
     */
    public function test_can_publish_version_and_archive_previous_ones()
    {
        $this->actingAs($this->tenantAdminA);

        $document = LegalDocument::create([
            'key' => 'terms-of-use',
            'company_id' => $this->companyA->id,
            'is_active' => true,
        ]);

        // الإصدار القديم المنشور حالياً
        $oldVersion = LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version' => '1.0',
            'title' => 'شروط القديمة',
            'content' => 'محتوى قديم',
            'status' => 'published',
            'company_id' => $this->companyA->id,
        ]);

        // الإصدار الجديد كمسودة
        $newVersion = LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version' => '2.0',
            'title' => 'شروط المحدثة',
            'content' => 'محتوى جديد',
            'status' => 'draft',
            'company_id' => $this->companyA->id,
        ]);

        $response = $this->postJson("/api/v1/legal/admin/versions/{$newVersion->id}/publish");

        $response->assertStatus(200);

        // تحقق من أرشفة الإصدار القديم
        $oldVersion->refresh();
        $this->assertEquals('archived', $oldVersion->status);

        // تحقق من نشر الإصدار الجديد
        $newVersion->refresh();
        $this->assertEquals('published', $newVersion->status);
        $this->assertNotNull($newVersion->published_at);
    }

    /**
     * اختبار التحقق من المستندات المعلقة وتوقيع الموافقة.
     */
    public function test_user_acceptance_flow()
    {
        $this->actingAs($this->tenantAdminA);

        $document = LegalDocument::create([
            'key' => 'cookie-policy',
            'company_id' => $this->companyA->id,
            'is_active' => true,
        ]);

        $version = LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version' => '1.0',
            'title' => 'سياسة الكوكيز',
            'content' => 'تفاصيل الكوكيز المحدثة',
            'status' => 'published',
            'published_at' => now(),
            'company_id' => $this->companyA->id,
        ]);

        // 1. التحقق من وجود موافقات معلقة
        $response = $this->getJson('/api/v1/legal/acceptances/pending');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($version->id, $response->json('data.0.id'));

        // 2. إرسال موافقة المستخدم
        $acceptResponse = $this->postJson('/api/v1/legal/acceptances', [
            'version_id' => $version->id,
        ]);

        $acceptResponse->assertStatus(201);
        $this->assertDatabaseHas('legal_document_acceptances', [
            'user_id' => $this->tenantAdminA->id,
            'legal_document_version_id' => $version->id,
        ]);

        // 3. التحقق مرة أخرى من الموافقات المعلقة (يجب أن تصبح فارغة)
        $responseAfter = $this->getJson('/api/v1/legal/acceptances/pending');
        $responseAfter->assertStatus(200);
        $responseAfter->assertJsonCount(0, 'data');
    }

    /**
     * اختبار عزل مستندات الشركة (Tenant Isolation).
     */
    public function test_company_data_isolation()
    {
        // 1. إنشاء مستند للشركة A بواسطة أدمن A
        $this->actingAs($this->tenantAdminA);
        $documentA = LegalDocument::create([
            'key' => 'privacy-policy',
            'company_id' => $this->companyA->id,
            'is_active' => true,
        ]);

        // 2. المحاولة بواسطة أدمن B (الشركة B) لعرض مستند الشركة A بالـ ID المباشر
        $this->actingAs($this->tenantAdminB);
        $response = $this->getJson("/api/v1/legal/admin/documents/{$documentA->id}");

        // يجب أن يرجع 404 لأن الفلتر التلقائي (global scope) يعزل البيانات
        $response->assertStatus(404);
    }

    /**
     * اختبار جلب الزوار للمستندات النشطة (العامة أو الخاصة بالشركة).
     */
    public function test_guest_can_fetch_active_documents()
    {
        // مستند عام (company_id = null)
        $globalDoc = LegalDocument::create([
            'key' => 'terms-of-use',
            'company_id' => null,
            'is_active' => true,
        ]);

        $version = LegalDocumentVersion::create([
            'legal_document_id' => $globalDoc->id,
            'version' => '1.0',
            'title' => 'الشروط العامة',
            'content' => 'شروط المنصة العامة لكافة المستخدمين والزوار',
            'status' => 'published',
            'published_at' => now(),
            'company_id' => null,
        ]);

        // جلب كزائر بدون تسجيل دخول
        $response = $this->getJson('/api/v1/legal/documents/terms-of-use/active');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'data' => [
                'id' => $version->id,
                'title' => 'الشروط العامة',
                'content' => 'شروط المنصة العامة لكافة المستخدمين والزوار',
            ]
        ]);
    }

    /**
     * اختبار تسجيل الأنشطة (Audit Logs / Activity Logs) عند اتخاذ إجراءات قانونية.
     */
    public function test_activity_logs_for_legal_actions()
    {
        $this->actingAs($this->tenantAdminA);

        // إنشاء مستند جديد
        $document = LegalDocument::create([
            'key' => 'terms-of-use',
            'company_id' => $this->companyA->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'انشاء',
            'model' => LegalDocument::class,
            'row_id' => $document->id,
        ]);
    }
}
