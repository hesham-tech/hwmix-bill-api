<?php

namespace Modules\ExportImport\tests\Feature;

// تعليق عربي: اختبارات التحقق من صحة وجدولة وأمان عمليات التصدير والاستيراد بالخلفية.

use App\Models\User;
use App\Models\Company;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Modules\ExportImport\Models\ExportImportJob;
use Modules\ExportImport\Jobs\QueuedExportJob;
use Modules\ExportImport\Jobs\QueuedImportJob;

class ExportImportTest extends TestCase
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
     * اختبار جدولة مهمة تصدير وإطلاق الـ Job بالخلفية.
     */
    public function test_can_queue_export_job()
    {
        $this->actingAs($this->admin);
        Queue::fake();

        $response = $this->postJson('/api/v1/export-import/export', [
            'model_type' => 'products'
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'status' => true,
            'message' => 'تم جدولة عملية التصدير بالخلفية بنجاح.'
        ]);

        $this->assertDatabaseHas('export_import_jobs', [
            'company_id' => $this->company->id,
            'type' => 'export',
            'model_type' => 'products',
            'status' => 'pending'
        ]);

        Queue::assertPushed(QueuedExportJob::class);
    }

    /**
     * اختبار تنفيذ الـ Export Job وتوليد ملف CSV بنجاح.
     */
    public function test_queued_export_job_executes_successfully()
    {
        Storage::fake('public');

        // إنشاء بعض المنتجات الوهمية للتصدير بالحقول الفعلية المتوافقة مع قاعدة البيانات
        \Illuminate\Support\Facades\DB::table('products')->insert([
            [
                'name' => 'Product A',
                'slug' => 'product-a',
                'desc' => 'Description A',
                'active' => 1,
                'featured' => 0,
                'returnable' => 1,
                'category_id' => null,
                'company_id' => $this->company->id,
                'created_by' => $this->admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Product B',
                'slug' => 'product-b',
                'desc' => 'Description B',
                'active' => 1,
                'featured' => 0,
                'returnable' => 1,
                'category_id' => null,
                'company_id' => $this->company->id,
                'created_by' => $this->admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // إنشاء سجل المهمة
        $job = ExportImportJob::create([
            'type' => 'export',
            'model_type' => 'products',
            'status' => 'pending',
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id
        ]);

        // تشغيل الـ Job يدوياً
        $jobInstance = new QueuedExportJob($job->id);
        $jobInstance->handle();

        // جلب السجل المحدث
        $updatedJob = $job->fresh();
        $this->assertEquals('completed', $updatedJob->status);
        $this->assertEquals(100, $updatedJob->progress);
        $this->assertNotNull($updatedJob->file_path);

        // التحقق من وجود الملف في التخزين المزيّف ومحتوياته
        Storage::disk('public')->assertExists($updatedJob->file_path);
        $content = Storage::disk('public')->get($updatedJob->file_path);
        
        $this->assertStringContainsString('Product A', $content);
        $this->assertStringContainsString('Description A', $content);
        $this->assertStringContainsString('Product B', $content);
    }

    /**
     * اختبار تحميل الملف الناتج بشكل آمن.
     */
    public function test_can_download_exported_file_securely()
    {
        Storage::fake('public');
        $this->actingAs($this->admin);

        $fileName = 'exports/' . $this->company->id . '/test_export.csv';
        Storage::disk('public')->put($fileName, 'ID,Name,Description');

        $job = ExportImportJob::create([
            'type' => 'export',
            'model_type' => 'products',
            'status' => 'completed',
            'file_path' => $fileName,
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id
        ]);

        $response = $this->getJson("/api/v1/export-import/download/{$job->id}");

        $response->assertStatus(200);
        $this->assertEquals('attachment; filename=test_export.csv', $response->headers->get('content-disposition'));
    }

    /**
     * اختبار جدولة مهمة استيراد ورفع الملف.
     */
    public function test_can_queue_import_job()
    {
        Storage::fake('public');
        $this->actingAs($this->admin);
        Queue::fake();

        // إنشاء ملف CSV وهمي متوافق مع الحقول الجديدة
        $file = UploadedFile::fake()->createWithContent('products.csv', "ID,الاسم,الوصف,مفعل\n1,Product Import,Import Description,نعم");

        $response = $this->postJson('/api/v1/export-import/import', [
            'model_type' => 'products',
            'file' => $file
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'status' => true,
            'message' => 'تم رفع الملف وجدولة عملية الاستيراد بالخلفية بنجاح.'
        ]);

        $job = ExportImportJob::where('type', 'import')->first();
        $this->assertNotNull($job);
        $this->assertEquals('products', $job->model_type);
        Storage::disk('public')->assertExists($job->file_path);

        Queue::assertPushed(QueuedImportJob::class);
    }

    /**
     * اختبار تشغيل الـ Import Job الخلفي وإدراج البيانات الفعلي.
     */
    public function test_queued_import_job_executes_successfully()
    {
        Storage::fake('public');

        $fileName = 'imports/' . $this->company->id . '/test_import.csv';
        // إضافة ترميز BOM
        $content = chr(0xEF).chr(0xBB).chr(0xBF) . "ID,الاسم,الوصف,مفعل\n1,Product Import Test,Import Test Description,نعم";
        Storage::disk('public')->put($fileName, $content);

        $job = ExportImportJob::create([
            'type' => 'import',
            'model_type' => 'products',
            'status' => 'pending',
            'file_path' => $fileName,
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id
        ]);

        // تشغيل الـ Job
        $jobInstance = new QueuedImportJob($job->id);
        $jobInstance->handle();

        $updatedJob = $job->fresh();
        $this->assertEquals('completed', $updatedJob->status);
        $this->assertEquals(100, $updatedJob->progress);
        $this->assertEquals(1, $updatedJob->errors['success_count']);

        // التحقق من إدراج المنتج في قاعدة البيانات
        $this->assertDatabaseHas('products', [
            'name' => 'Product Import Test',
            'desc' => 'Import Test Description',
            'company_id' => $this->company->id
        ]);
    }
}
