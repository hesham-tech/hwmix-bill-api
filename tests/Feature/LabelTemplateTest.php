<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Inventory\Models\LabelTemplate;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductVariant;
use Modules\Inventory\Services\LabelTemplateBootstrapper;
use Modules\Inventory\Services\LabelTemplateDesign;
use Modules\Media\Models\MediaFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LabelTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // The existing media library uses MySQL CONCAT; supply its equivalent in SQLite tests.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', fn (...$parts) => in_array(null, $parts, true) ? null : implode('', $parts));
        }
        config(['label_templates.enabled' => true, 'label_templates.company_ids' => []]);
        $this->company = Company::withoutEvents(fn () => Company::factory()->create());
        LabelTemplateBootstrapper::ensure($this->company->id);
        $this->admin = User::withoutEvents(fn () => User::factory()->create(['company_id' => $this->company->id, 'active_company_id' => $this->company->id]));
        foreach (['admin.super', 'admin.company', 'images.view_all', 'images.delete_all', 'invoices.view_all', 'invoices.view_self', 'invoices.view_children', 'products.print_labels', 'products.view_wholesale_price', 'products.view_purchase_price', 'label_templates.manage', 'label_templates.publish', 'label_templates.set_default'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        setPermissionsTeamId($this->company->id);
        $this->admin->givePermissionTo('admin.super');
        $this->actingAs($this->admin);
    }

    private function createTemplate(?array $design = null): array
    {
        return $this->postJson('/api/v1/label-templates', ['name' => 'قالب اختبار', 'design' => $design ?? LabelTemplateDesign::starter()])->assertCreated()->json('data');
    }

    private function publishTemplate(array $template): array
    {
        return $this->postJson('/api/v1/label-templates/'.$template['id'].'/publish', ['lock_version' => $template['lock_version']])->assertOk()->json('data');
    }

    private function printer(): User
    {
        $user = User::withoutEvents(fn () => User::factory()->create(['company_id' => $this->company->id, 'active_company_id' => $this->company->id]));
        $user->givePermissionTo('products.print_labels');

        return $user;
    }

    public function test_metadata_is_atomic_and_does_not_create_a_design_revision(): void
    {
        $template = $this->publishTemplate($this->createTemplate());
        $count = DB::table('label_template_revisions')->count();
        $updated = $this->patchJson('/api/v1/label-templates/'.$template['id'].'/metadata', [
            'name' => 'اسم جديد', 'description' => 'قالب للملابس', 'make_default' => true, 'lock_version' => $template['lock_version'],
        ])->assertOk()->assertJsonPath('data.name', 'اسم جديد')->assertJsonPath('data.description', 'قالب للملابس')->assertJsonPath('data.is_default', true)->json('data');
        $this->assertFalse($updated['has_draft']);
        $this->assertSame($template['published_revision_id'], $updated['published_revision_id']);
        $this->assertSame($count, DB::table('label_template_revisions')->count());
        $this->patchJson('/api/v1/label-templates/'.$template['id'].'/metadata', ['name' => 'متأخر', 'lock_version' => $template['lock_version']])->assertStatus(409);
        $design = $template['published']['design'];
        $design['elements'][0]['content'] = 'مسودة تصميم';
        $this->putJson('/api/v1/label-templates/'.$template['id'], ['design' => $design, 'lock_version' => $updated['lock_version']])->assertOk()->assertJsonPath('data.name', 'اسم جديد');
    }

    public function test_metadata_default_failure_rolls_back_name_and_description(): void
    {
        $template = $this->createTemplate();
        $this->patchJson('/api/v1/label-templates/'.$template['id'].'/metadata', ['name' => 'لا تحفظ', 'description' => 'لا تحفظ', 'make_default' => true, 'lock_version' => $template['lock_version']])->assertStatus(422);
        $this->assertDatabaseHas('label_templates', ['id' => $template['id'], 'name' => $template['name'], 'description' => null, 'lock_version' => $template['lock_version']]);
    }

    public function test_metadata_obeys_company_isolation_and_permissions(): void
    {
        $template = $this->createTemplate();
        $printer = $this->printer();
        $this->actingAs($printer)->patchJson('/api/v1/label-templates/'.$template['id'].'/metadata', ['name' => 'ممنوع', 'lock_version' => 1])->assertForbidden();
        $this->actingAs($this->admin);
        $other = Company::withoutEvents(fn () => Company::factory()->create());
        LabelTemplateBootstrapper::ensure($other->id);
        $foreign = LabelTemplate::where('company_id', $other->id)->first();
        $this->patchJson('/api/v1/label-templates/'.$foreign->id.'/metadata', ['name' => 'ممنوع', 'lock_version' => 1])->assertNotFound();
        $this->patchJson('/api/v1/label-templates/'.$template['id'].'/metadata', ['name' => 'ممنوع', 'company_id' => $other->id, 'lock_version' => 1])->assertUnprocessable();
    }

    public function test_blank_template_is_a_valid_draft_but_cannot_be_published(): void
    {
        $design = LabelTemplateDesign::starter();
        $design['elements'] = [];
        $template = $this->createTemplate($design);
        $this->postJson('/api/v1/label-templates/'.$template['id'].'/publish', ['lock_version' => $template['lock_version']])->assertUnprocessable();
    }

    public function test_flex_and_price_format_round_trip_with_restricted_price_permissions(): void
    {
        $design = LabelTemplateDesign::starter();
        $design['schemaVersion'] = 2;
        $design['elements'] = [
            ['id' => 'row', 'type' => 'row', 'x' => 3, 'y' => 3, 'width' => 44, 'height' => 12, 'rotation' => 0, 'layout' => ['direction' => 'rtl', 'mode' => 'auto', 'align' => 'center', 'wrap' => false, 'paddingMm' => 1, 'gapMm' => 1, 'rowGapMm' => 1]],
            ['id' => 'price', 'type' => 'text', 'x' => 0, 'y' => 0, 'width' => 20, 'height' => 6, 'parentId' => 'row', 'order' => 0, 'content' => '{{variant.retail_price}}', 'priceFormat' => ['decimals' => 0, 'showCurrency' => false, 'trimZeros' => true, 'groupThousands' => false, 'currencySymbol' => '', 'currencyPosition' => 'after']],
        ];
        $template = $this->createTemplate($design);
        $this->assertSame(0, $template['draft']['design']['elements'][1]['priceFormat']['decimals']);
        $this->assertSame('row', $template['draft']['design']['elements'][1]['parentId']);
        $this->publishTemplate($template);
        $design['elements'][1]['content'] = '{{variant.wholesale_price}}';
        $manager = $this->printer();
        $manager->givePermissionTo('label_templates.manage');
        $this->actingAs($manager)->postJson('/api/v1/label-templates', ['name' => 'ممنوع', 'design' => $design])->assertUnprocessable();
    }

    public function test_invalid_row_parent_nested_rows_and_invalid_price_precision_are_rejected(): void
    {
        $design = LabelTemplateDesign::starter();
        $design['schemaVersion'] = 2;
        $design['elements'][0]['parentId'] = 'missing';
        $this->postJson('/api/v1/label-templates', ['name' => 'خطأ', 'design' => $design])->assertUnprocessable();
        unset($design['elements'][0]['parentId']);
        $design['elements'][2]['priceFormat'] = ['decimals' => 7];
        $this->postJson('/api/v1/label-templates', ['name' => 'خطأ', 'design' => $design])->assertUnprocessable();
        $design['elements'][2]['priceFormat'] = ['decimals' => 2];
        $design['elements'][2]['content'] = '{{variant.sku}}';
        $this->postJson('/api/v1/label-templates', ['name' => 'خطأ', 'design' => $design])->assertUnprocessable();
    }

    public function test_an_empty_flex_row_can_be_saved_but_not_published(): void
    {
        $design = LabelTemplateDesign::starter();
        $design['schemaVersion'] = 2;
        $design['elements'] = [['id' => 'row', 'type' => 'row', 'x' => 3, 'y' => 3, 'width' => 44, 'height' => 12]];
        $template = $this->createTemplate($design);
        $this->postJson('/api/v1/label-templates/'.$template['id'].'/publish', ['lock_version' => $template['lock_version']])->assertUnprocessable();
    }

    public function test_editor_exposes_actual_company_currency_without_assuming_a_default(): void
    {
        $this->company->update(['settings' => []]);
        $this->getJson('/api/v1/label-templates/fields')->assertOk()->assertJsonPath('currency_symbol', null);
        $this->company->update(['settings' => ['currency_symbol' => 'ر.س']]);
        $this->getJson('/api/v1/label-templates/fields')->assertOk()->assertJsonPath('currency_symbol', 'ر.س');
    }

    public function test_company_starter_is_published_default_and_bootstrap_is_idempotent(): void
    {
        $this->getJson('/api/v1/label-templates?published_only=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_default', true);
        LabelTemplateBootstrapper::ensure($this->company->id);
        $this->assertSame(1, LabelTemplate::where('company_id', $this->company->id)->count());
    }

    public function test_draft_changes_do_not_modify_published_revision_and_publish_is_explicit(): void
    {
        $template = $this->publishTemplate($this->createTemplate());
        $publishedId = $template['published_revision_id'];
        $design = $template['published']['design'];
        $design['elements'][0]['content'] = 'تعديل مسودة';
        $updated = $this->putJson('/api/v1/label-templates/'.$template['id'], ['name' => 'مسودة معدلة', 'design' => $design, 'lock_version' => $template['lock_version']])->assertOk()->json('data');
        $this->assertSame($publishedId, $updated['published_revision_id']);
        $this->assertSame('{{company.name}}', $updated['published']['design']['elements'][0]['content']);
        $this->assertSame('تعديل مسودة', $updated['draft']['design']['elements'][0]['content']);
        $published = $this->publishTemplate($updated);
        $this->assertNotSame($publishedId, $published['published_revision_id']);
        $this->assertSame(2, count($published['revisions']));
        $this->assertFalse($published['has_draft']);
    }

    public function test_stale_save_returns_conflict_without_overwriting(): void
    {
        $template = $this->createTemplate();
        $payload = ['name' => 'الأول', 'design' => LabelTemplateDesign::starter(), 'lock_version' => $template['lock_version']];
        $this->putJson('/api/v1/label-templates/'.$template['id'], $payload)->assertOk();
        $payload['name'] = 'كتابة متأخرة';
        $this->putJson('/api/v1/label-templates/'.$template['id'], $payload)->assertStatus(409);
        $this->assertDatabaseHas('label_templates', ['id' => $template['id'], 'name' => 'الأول']);
    }

    public function test_super_admin_still_cannot_read_or_modify_other_company_templates(): void
    {
        $other = Company::withoutEvents(fn () => Company::factory()->create());
        LabelTemplateBootstrapper::ensure($other->id);
        $id = LabelTemplate::where('company_id', $other->id)->value('id');
        $this->getJson('/api/v1/label-templates/'.$id)->assertNotFound();
        $this->postJson('/api/v1/label-templates/'.$id.'/duplicate')->assertNotFound();
        $this->patchJson('/api/v1/label-templates/'.$id.'/default')->assertNotFound();
        $this->postJson('/api/v1/label-templates/'.$id.'/publish', ['lock_version' => 1])->assertNotFound();
        $this->putJson('/api/v1/label-templates/'.$id, ['name' => 'غير مسموح', 'lock_version' => 1, 'design' => LabelTemplateDesign::starter()])->assertNotFound();
    }

    public function test_company_forgery_and_missing_active_company_are_rejected(): void
    {
        $this->postJson('/api/v1/label-templates', ['name' => 'مزوّر', 'company_id' => $this->company->id, 'design' => LabelTemplateDesign::starter()])->assertUnprocessable();
        $this->withHeader('X-HWNIX-COMPANY', (string) ($this->company->id + 999))->getJson('/api/v1/label-templates')->assertForbidden();
        $this->flushHeaders();
        $this->admin->forceFill(['active_company_id' => null])->saveQuietly();
        $this->getJson('/api/v1/label-templates')->assertStatus(409);
    }

    public function test_print_permission_does_not_grant_draft_or_design_access(): void
    {
        $draft = $this->createTemplate();
        $this->actingAs($this->printer());
        $this->getJson('/api/v1/label-templates')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/label-templates/'.$draft['id'])->assertNotFound();
        $this->postJson('/api/v1/label-templates', ['name' => 'ممنوع', 'design' => LabelTemplateDesign::starter()])->assertForbidden();
        $this->patchJson('/api/v1/label-templates/'.$this->company->fresh()->default_label_template_id.'/default')->assertForbidden();
    }

    public function test_restricted_price_binding_is_blocked_before_product_lookup(): void
    {
        $design = LabelTemplateDesign::starter();
        $design['elements'][0]['content'] = '{{variant.purchase_price}}';
        $template = $this->publishTemplate($this->createTemplate($design));
        $this->actingAs($this->printer());
        $this->getJson('/api/v1/label-templates/fields')->assertOk()->assertJsonMissing(['key' => 'variant.purchase_price']);
        $this->postJson('/api/v1/label-print-data', ['template_id' => $template['id'], 'revision_id' => $template['published_revision_id'], 'items' => [['variant_id' => 999999, 'quantity' => 1]]])->assertUnprocessable();
    }

    public function test_default_requires_published_template_and_archive_requires_replacement(): void
    {
        $draft = $this->createTemplate();
        $this->patchJson('/api/v1/label-templates/'.$draft['id'].'/default')->assertUnprocessable();
        $published = $this->publishTemplate($draft);
        $this->patchJson('/api/v1/label-templates/'.$published['id'].'/default')->assertOk();
        $this->deleteJson('/api/v1/label-templates/'.$published['id'], ['lock_version' => $published['lock_version']])->assertUnprocessable();
        $replacement = $this->publishTemplate($this->createTemplate());
        $this->deleteJson('/api/v1/label-templates/'.$published['id'], ['lock_version' => $published['lock_version'], 'replacement_id' => $replacement['id']])->assertOk();
        $this->assertSame($replacement['id'], (int) $this->company->fresh()->default_label_template_id);
        $this->assertDatabaseHas('label_templates', ['id' => $published['id'], 'archived' => true]);
    }

    public function test_restore_creates_draft_without_changing_current_publication(): void
    {
        $template = $this->publishTemplate($this->createTemplate());
        $result = $this->postJson('/api/v1/label-templates/'.$template['id'].'/restore', ['revision_id' => $template['published_revision_id'], 'lock_version' => $template['lock_version']])->assertOk()->json('data');
        $this->assertTrue($result['has_draft']);
        $this->assertSame($template['published_revision_id'], $result['published_revision_id']);
        $this->assertNotSame($result['draft']['id'], $result['published_revision_id']);
    }

    public function test_unknown_fields_and_unsafe_designs_cannot_be_saved_or_published(): void
    {
        $design = LabelTemplateDesign::starter();
        $design['elements'][0]['content'] = '{{product.license_keys}}';
        $this->postJson('/api/v1/label-templates', ['name' => 'محجوب', 'design' => $design])->assertUnprocessable();
        $design = LabelTemplateDesign::starter();
        $design['elements'][0]['fontFamily'] = 'url(evil)';
        $this->postJson('/api/v1/label-templates', ['name' => 'غير صالح', 'design' => $design])->assertUnprocessable();
        $design = LabelTemplateDesign::starter();
        $design['elements'][0]['x'] = 100;
        $template = $this->createTemplate($design);
        $this->postJson('/api/v1/label-templates/'.$template['id'].'/publish', ['lock_version' => $template['lock_version']])->assertUnprocessable();
    }

    public function test_referenced_asset_cannot_be_deleted_and_foreign_assets_are_rejected(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/label-test.png', 'image');
        $media = MediaFile::withoutEvents(fn () => MediaFile::create(['company_id' => $this->company->id, 'created_by' => $this->admin->id, 'filename' => 'label-test.png', 'original_name' => 'test.png', 'file_path' => 'media/label-test.png', 'file_size' => 5, 'mime_type' => 'image/png']));
        $design = LabelTemplateDesign::starter();
        $design['elements'][] = ['id' => 'image', 'type' => 'image', 'x' => 3, 'y' => 3, 'width' => 8, 'height' => 8, 'mediaId' => $media->id];
        $this->createTemplate($design);
        $this->deleteJson('/api/v1/media/'.$media->id)->assertStatus(409);
        Storage::disk('public')->assertExists('media/label-test.png');
        DB::table('media_files')->where('id', $media->id)->update(['company_id' => $this->company->id + 999]);
        $this->postJson('/api/v1/label-templates', ['name' => 'صورة شركة أخرى', 'design' => $design])->assertUnprocessable();
        $this->getJson('/api/v1/label-templates/assets/'.$media->id)->assertNotFound();
    }

    public function test_print_data_uses_current_retail_price_and_pins_published_revision(): void
    {
        $product = Product::withoutEvents(fn () => Product::factory()->create(['company_id' => $this->company->id, 'created_by' => $this->admin->id]));
        $variant = ProductVariant::withoutEvents(fn () => ProductVariant::factory()->create(['product_id' => $product->id, 'company_id' => $this->company->id, 'created_by' => $this->admin->id, 'barcode' => '0012345678', 'retail_price' => 125.75]));
        $template = $this->publishTemplate($this->createTemplate());
        $old = $template['published_revision_id'];
        $draft = $this->putJson('/api/v1/label-templates/'.$template['id'], ['name' => 'جديد', 'design' => LabelTemplateDesign::starter(), 'lock_version' => $template['lock_version']])->assertOk()->json('data');
        $this->publishTemplate($draft);
        $this->actingAs($this->printer());
        $result = $this->postJson('/api/v1/label-print-data', ['template_id' => $template['id'], 'revision_id' => $old, 'items' => [['variant_id' => $variant->id, 'quantity' => 2]]])->assertOk()->json('data');
        $this->assertSame($old, $result['revision_id']);
        $this->assertSame('125.75', $result['records'][0]['values']['variant.retail_price']);
        $this->assertSame('0012345678', $result['records'][0]['values']['variant.barcode']);
        $this->assertArrayNotHasKey('variant.purchase_price', $result['records'][0]['values']);
    }

    public function test_feature_flag_blocks_unreleased_company(): void
    {
        config(['label_templates.company_ids' => [$this->company->id + 1]]);
        $this->getJson('/api/v1/label-templates')->assertStatus(503);
        $this->getJson('/api/v1/label-templates?published_only=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/label-templates/fields')->assertOk()->assertJsonPath('editor_enabled', false)->assertJsonPath('permissions.manage', false);
        $this->postJson('/api/v1/label-templates', ['name' => 'غير مفعل', 'design' => LabelTemplateDesign::starter()])->assertStatus(503);
    }

    public function test_invoice_label_uses_current_product_price_and_exposes_invoice_price_separately(): void
    {
        $product = Product::withoutEvents(fn () => Product::factory()->create(['company_id' => $this->company->id, 'created_by' => $this->admin->id]));
        $variant = ProductVariant::withoutEvents(fn () => ProductVariant::factory()->create(['product_id' => $product->id, 'company_id' => $this->company->id, 'created_by' => $this->admin->id, 'retail_price' => 125.75]));
        $invoice = \Modules\Sales\Models\Invoice::withoutEvents(fn () => \Modules\Sales\Models\Invoice::factory()->create(['company_id' => $this->company->id, 'created_by' => $this->admin->id, 'user_id' => $this->admin->id]));
        $item = \Modules\Sales\Models\InvoiceItem::withoutEvents(fn () => \Modules\Sales\Models\InvoiceItem::factory()->create(['company_id' => $this->company->id, 'created_by' => $this->admin->id, 'invoice_id' => $invoice->id, 'product_id' => $product->id, 'variant_id' => $variant->id, 'unit_price' => 50]));
        $template = $this->publishTemplate($this->createTemplate());
        $result = $this->postJson('/api/v1/label-print-data', ['template_id' => $template['id'], 'revision_id' => $template['published_revision_id'], 'items' => [['invoice_item_id' => $item->id, 'quantity' => 1]]])->assertOk()->json('data.records.0.values');
        $this->assertSame('125.75', $result['variant.retail_price']);
        $this->assertSame(50, $result['invoice.unit_price']);
        $this->postJson('/api/v1/label-print-data', ['template_id' => $template['id'], 'revision_id' => $template['published_revision_id'], 'items' => [['invoice_item_id' => $item->id, 'variant_id' => $variant->id + 999, 'quantity' => 1]]])->assertUnprocessable();
    }

    public function test_print_batch_rejects_foreign_variants_and_unrelated_units(): void
    {
        $other = Company::withoutEvents(fn () => Company::factory()->create());
        $product = Product::withoutEvents(fn () => Product::factory()->create(['company_id' => $other->id]));
        $variant = ProductVariant::withoutEvents(fn () => ProductVariant::factory()->create(['company_id' => $other->id, 'product_id' => $product->id]));
        $template = $this->publishTemplate($this->createTemplate());
        $this->postJson('/api/v1/label-print-data', ['template_id' => $template['id'], 'revision_id' => $template['published_revision_id'], 'items' => [['variant_id' => $variant->id, 'quantity' => 1]]])->assertNotFound();
        $ownedProduct = Product::withoutEvents(fn () => Product::factory()->create(['company_id' => $this->company->id]));
        $owned = ProductVariant::withoutEvents(fn () => ProductVariant::factory()->create(['company_id' => $this->company->id, 'product_id' => $ownedProduct->id]));
        $this->postJson('/api/v1/label-print-data', ['template_id' => $template['id'], 'revision_id' => $template['published_revision_id'], 'items' => [['variant_id' => $owned->id, 'unit_id' => 999999, 'quantity' => 1]]])->assertUnprocessable();
    }

    public function test_preview_does_not_publish_and_requires_designer_access(): void
    {
        $this->actingAs($this->printer());
        $this->postJson('/api/v1/label-template-preview-data', ['design' => LabelTemplateDesign::starter(), 'items' => [['variant_id' => 1, 'quantity' => 1]]])->assertForbidden();
        $this->assertSame(1, LabelTemplate::where('company_id', $this->company->id)->count());
    }

    public function test_uploaded_template_asset_is_private_and_served_only_in_company_context(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $file = \Illuminate\Http\UploadedFile::fake()->image('logo.png', 80, 80);
        $id = $this->post('/api/v1/label-templates/assets', ['file' => $file], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $media = MediaFile::withoutGlobalScopes()->findOrFail($id);
        $this->assertStringStartsWith('label-template-assets/'.$this->company->id.'/', $media->file_path);
        Storage::disk('local')->assertExists($media->file_path);
        Storage::disk('public')->assertMissing($media->file_path);
        $this->get('/api/v1/label-templates/assets/'.$id, ['Accept' => 'application/json'])->assertOk();
        $this->getJson('/api/v1/media')->assertOk()->assertJsonCount(0, 'data');
        $this->admin->forceFill(['active_company_id' => null])->saveQuietly();
        $this->getJson('/api/v1/label-templates/assets/'.$id)->assertStatus(409);
    }

    public function test_label_product_lookup_batches_only_current_company_data_without_prices(): void
    {
        $product = Product::withoutEvents(fn () => Product::factory()->create(['company_id' => $this->company->id, 'created_by' => $this->admin->id]));
        $variant = ProductVariant::withoutEvents(fn () => ProductVariant::factory()->create(['company_id' => $this->company->id, 'product_id' => $product->id, 'created_by' => $this->admin->id]));
        $this->actingAs($this->printer());
        $result = $this->getJson('/api/v1/label-templates/products?'.http_build_query(['variant_ids' => [$variant->id]]))->assertOk()->assertJsonCount(1, 'data')->json('data.0.variants.0');
        $this->assertSame($variant->id, $result['id']);
        $this->assertArrayNotHasKey('retail_price', $result);
        $this->assertArrayNotHasKey('purchase_price', $result);
        $this->getJson('/api/v1/label-templates/products?'.http_build_query(['ids' => [$product->id, 999999]]))->assertNotFound();
        $this->getJson('/api/v1/label-templates/not-an-id')->assertNotFound();
    }
}
