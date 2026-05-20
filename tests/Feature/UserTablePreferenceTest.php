<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\UserTablePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اختبارات التحقق من صحة تفضيلات الجداول وأمن الإعدادات وعزل الشركات.
 */
class UserTablePreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(\Database\Seeders\AddPermissionsSeeder::class);
        
        // إنشاء شركة ومستخدم افتراضي للتجربة
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'active_company_id' => $this->company->id
        ]);
        
        $this->actingAs($this->user);
    }

    /**
     * اختبار إمكانية حفظ تفضيلات صالحة لجدول مصرح به.
     */
    public function test_user_can_save_valid_table_preferences(): void
    {
        $payload = [
            'table_key' => 'products.index',
            'preferences' => [
                'columns' => [
                    ['key' => 'name', 'visible' => true],
                    ['key' => 'sku', 'visible' => false],
                ],
                'itemsPerPage' => 25,
            ]
        ];

        $response = $this->postJson('/api/v1/ui-preferences', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.columns.0.key', 'name')
            ->assertJsonPath('data.itemsPerPage', 25);

        $this->assertDatabaseHas('user_table_preferences', [
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'table_key' => 'products.index',
        ]);
    }

    /**
     * اختبار منع حفظ تفضيلات لجدول غير مصرح به (Security Check).
     */
    public function test_user_cannot_save_preferences_for_unauthorized_table(): void
    {
        $payload = [
            'table_key' => 'unauthorized_table_key',
            'preferences' => [
                'columns' => [
                    ['key' => 'name', 'visible' => true]
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/ui-preferences', $payload);

        $response->assertStatus(422);
    }

    /**
     * اختبار منع حفظ تفضيلات تحتوي أعمدة غير مصرح بها للجدول (Security Check).
     */
    public function test_user_cannot_save_invalid_columns(): void
    {
        $payload = [
            'table_key' => 'products.index',
            'preferences' => [
                'columns' => [
                    ['key' => 'invalid_column_key_here', 'visible' => true]
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/ui-preferences', $payload);

        $response->assertStatus(422);
    }

    /**
     * اختبار جلب التفضيلات بنجاح.
     */
    public function test_user_can_fetch_preferences(): void
    {
        UserTablePreference::create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'table_key' => 'products.index',
            'preferences' => ['columns' => [['key' => 'name', 'visible' => true]]],
        ]);

        $response = $this->getJson('/api/v1/ui-preferences?keys=products.index');

        $response->assertStatus(200);
        $this->assertEquals('name', data_get($response->json(), ['data', 'products.index', 'columns', 0, 'key']));
    }

    /**
     * اختبار إعادة ضبط تفضيلات جدول محدد.
     */
    public function test_user_can_reset_preferences(): void
    {
        UserTablePreference::create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'table_key' => 'products.index',
            'preferences' => ['columns' => [['key' => 'name', 'visible' => true]]],
        ]);

        $response = $this->deleteJson('/api/v1/ui-preferences/products.index');

        $response->assertStatus(200);
        $this->assertDatabaseMissing('user_table_preferences', [
            'table_key' => 'products.index'
        ]);
    }

    /**
     * اختبار عزل تفضيلات الجداول بين الشركات والمستخدمين المختلفة (Multi-Tenant Isolation).
     */
    public function test_preferences_multi_tenant_isolation(): void
    {
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create([
            'active_company_id' => $otherCompany->id
        ]);

        UserTablePreference::create([
            'user_id' => $otherUser->id,
            'company_id' => $otherCompany->id,
            'table_key' => 'products.index',
            'preferences' => ['columns' => [['key' => 'sku', 'visible' => true]]],
        ]);

        // المستخدم الحالي يجب ألا يرى تفضيلات مستخدم الشركة الأخرى
        $response = $this->getJson('/api/v1/ui-preferences?keys=products.index');

        $response->assertStatus(200)
            ->assertJsonMissingPath('data.products\.index');
    }

    /**
     * اختبار مسار الـ Bootstrap.
     */
    public function test_bootstrap_endpoint(): void
    {
        UserTablePreference::create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'table_key' => 'products.index',
            'preferences' => ['columns' => [['key' => 'name', 'visible' => true]]],
        ]);

        $response = $this->getJson('/api/v1/bootstrap');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'user',
                    'screen_preferences',
                    'feature_flags',
                ]
            ]);
        $this->assertEquals('name', data_get($response->json(), ['data', 'screen_preferences', 'products.index', 'columns', 0, 'key']));
    }
}
