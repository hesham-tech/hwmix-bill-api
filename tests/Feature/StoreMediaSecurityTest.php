<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Services\{MediaStorageService,SvgSanitizer};
class StoreMediaSecurityTest extends TestCase {
    use RefreshDatabase;
    public function test_svg_is_sanitized_before_public_storage_without_breaking_local_logo_shapes(): void {
        Storage::fake('public');
        $company=\App\Models\Company::factory()->create();
        $svg='<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script><foreignObject><div>bad</div></foreignObject><image href="https://evil.example/tracker"/><defs><linearGradient id="safe"><stop offset="0"/></linearGradient></defs><rect width="10" height="10" fill="url(#safe)"/></svg>';
        $file=UploadedFile::fake()->createWithContent('logo.svg',$svg);
        $media=app(MediaStorageService::class)->store($file,$company->id);
        $stored=Storage::disk('public')->get($media->file_path);
        $this->assertSame('image/svg+xml',$media->mime_type);
        foreach(['<script','onload','foreignObject','evil.example'] as $unsafe)$this->assertStringNotContainsString($unsafe,$stored);
        $this->assertStringContainsString('url(#safe)',$stored);
    }
    public function test_malformed_svg_is_rejected(): void {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(SvgSanitizer::class)->clean('<svg><broken>');
    }
    public function test_primary_image_cannot_be_changed_on_another_product(): void {
        $product=\Modules\Inventory\Models\Product::factory()->create();
        $other=\Modules\Inventory\Models\Product::factory()->create();
        $user=\App\Models\User::factory()->create();
        $image=\App\Models\Image::create(['created_by'=>$user->id,'company_id'=>$other->company_id,'imageable_type'=>get_class($other),'imageable_id'=>$other->id,'url'=>'example.webp','is_primary'=>false]);
        \App\Services\ImageService::handlePrimaryImage($product,[$image->id],$image->id);
        $this->assertFalse($image->refresh()->is_primary);
    }
}
