<?php

namespace Modules\Media\tests\Feature;

//   اختبارات التحقق من صحة وأمان رفع الصور وضغطها وإدارة الملفات وعزلها بين الشركات.

use App\Models\User;
use App\Models\Company;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Modules\Media\Models\MediaFile;

class MediaTest extends TestCase
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
     * اختبار رفع صورة وتحويلها تلقائياً إلى صيغة WebP الفائقة.
     */
    public function test_can_upload_and_optimize_image_to_webp()
    {
        Storage::fake('public');
        $this->actingAs($this->admin);

        // إنشاء صورة JPEG وهمية
        $image = UploadedFile::fake()->image('avatar.jpg', 800, 600);

        $response = $this->postJson('/api/v1/media/upload', [
            'file' => $image
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => true,
            'message' => 'تم رفع ملف الوسائط بنجاح'
        ]);

        $mediaFile = MediaFile::where('company_id', $this->company->id)->first();
        $this->assertNotNull($mediaFile);

        // التحقق من تحويل الملف إلى WebP وتغيير اسمه ونوعه
        $this->assertEquals('image/webp', $mediaFile->mime_type);
        $this->assertStringEndsWith('.webp', $mediaFile->filename);

        Storage::disk('public')->assertExists($mediaFile->file_path);
    }

    /**
     * اختبار رفع ملف غير صوري (مثل PDF) وحفظه دون أي معالجة أو تحويل.
     */
    public function test_can_upload_non_image_file_without_processing()
    {
        Storage::fake('public');
        $this->actingAs($this->admin);

        // إنشاء ملف PDF وهمي
        $pdf = UploadedFile::fake()->create('report.pdf', 500, 'application/pdf');

        $response = $this->postJson('/api/v1/media/upload', [
            'file' => $pdf
        ]);

        $response->assertStatus(201);

        $mediaFile = MediaFile::where('company_id', $this->company->id)->first();
        $this->assertNotNull($mediaFile);

        // التحقق من بقاء التنسيق الأصلي للملف
        $this->assertEquals('application/pdf', $mediaFile->mime_type);
        $this->assertEquals('report.pdf', $mediaFile->original_name);

        Storage::disk('public')->assertExists($mediaFile->file_path);
    }

    /**
     * اختبار استعراض مكتبة الوسائط وعزل الملفات لكل شركة بشكل كامل.
     */
    public function test_can_list_media_files_with_company_isolation()
    {
        Storage::fake('public');
        $this->actingAs($this->admin);

        // إنشاء ملف للشركة الحالية
        MediaFile::create([
            'filename' => 'my_file.webp',
            'original_name' => 'my_file.webp',
            'file_path' => 'media/' . $this->company->id . '/my_file.webp',
            'file_size' => 1024,
            'mime_type' => 'image/webp',
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id
        ]);

        // إنشاء شركة أخرى وملف وسائط يخصها
        $otherCompany = Company::factory()->create();
        MediaFile::create([
            'filename' => 'other_file.webp',
            'original_name' => 'other_file.webp',
            'file_path' => 'media/' . $otherCompany->id . '/other_file.webp',
            'file_size' => 1024,
            'mime_type' => 'image/webp',
            'company_id' => $otherCompany->id
        ]);

        $response = $this->getJson('/api/v1/media');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['filename' => 'my_file.webp']);
        $response->assertJsonMissing(['filename' => 'other_file.webp']);
    }

    /**
     * اختبار حذف الملف من قاعدة البيانات ومسحه فعلياً من القرص.
     */
    public function test_can_delete_media_file_physically_from_storage()
    {
        Storage::fake('public');
        $this->actingAs($this->admin);

        $path = 'media/' . $this->company->id . '/delete_me.webp';
        Storage::disk('public')->put($path, 'dummy_content');

        $mediaFile = MediaFile::create([
            'filename' => 'delete_me.webp',
            'original_name' => 'delete_me.webp',
            'file_path' => $path,
            'file_size' => 13,
            'mime_type' => 'image/webp',
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id
        ]);

        Storage::disk('public')->assertExists($path);

        $response = $this->deleteJson("/api/v1/media/{$mediaFile->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => 'تم حذف ملف الوسائط بنجاح'
        ]);

        // التحقق من الحذف من قاعدة البيانات (Soft Delete)
        $this->assertSoftDeleted('media_files', ['id' => $mediaFile->id]);

        // التحقق من مسح الملف الفعلي من وحدة التخزين
        Storage::disk('public')->assertMissing($path);
    }
}
