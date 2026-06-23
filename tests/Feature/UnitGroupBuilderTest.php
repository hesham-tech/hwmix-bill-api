<?php
// اختبار تكاملي للتحقق من معمارية وسلامة الـ Unit Group Builder والتحويلات والـ Templates وعزل الشركات
namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitConversion;
use Database\Seeders\AddPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class UnitGroupBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin1;
    protected Company $company1;
    protected User $admin2;
    protected Company $company2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AddPermissionsSeeder::class);

        // الشركة الأولى ومستخدمها
        $this->company1 = Company::factory()->create();
        $this->admin1 = User::factory()->create([
            'company_id' => $this->company1->id,
        ]);
        $this->admin1->givePermissionTo('admin.super');

        // الشركة الثانية ومستخدمها
        $this->company2 = Company::factory()->create();
        $this->admin2 = User::factory()->create([
            'company_id' => $this->company2->id,
        ]);
        $this->admin2->givePermissionTo('admin.super');

        // إنشاء System Unit Groups (بدون company_id) لتكون بمثابة Templates
        $weightGroup = UnitGroup::create([
            'name' => 'وحدات الوزن النظامية',
            'type' => 'weight',
            'company_id' => null,
        ]);

        $gUnit = Unit::create([
            'unit_group_id' => $weightGroup->id,
            'name' => 'جرام نظامي',
            'code' => 'g',
            'decimal_places' => 3,
            'company_id' => null,
        ]);

        $kgUnit = Unit::create([
            'unit_group_id' => $weightGroup->id,
            'name' => 'كيلوجرام نظامي',
            'code' => 'kg',
            'decimal_places' => 3,
            'company_id' => null,
        ]);

        UnitConversion::create([
            'unit_group_id' => $weightGroup->id,
            'from_unit_id' => $kgUnit->id,
            'to_unit_id' => $gUnit->id,
            'factor' => 1000.0,
            'reverse_factor' => 0.001,
            'company_id' => null,
        ]);
    }

    /**
     * اختبار إنشاء مجموعة وحدات ناجح مع وحداتها وتحويلاتها في طلب واحد.
     */
    public function test_can_build_unit_group_with_units_and_conversions()
    {
        $this->actingAs($this->admin1);

        $tempUuidKg = (string) Str::uuid();
        $tempUuidG  = (string) Str::uuid();

        $payload = [
            'name' => 'مجموعة الوزن المخصصة',
            'type' => 'weight',
            'units' => [
                [
                    'temp_uuid' => $tempUuidKg,
                    'name' => 'كيلو جرام',
                    'code' => 'kg',
                    'decimal_places' => 3,
                    'is_active' => true,
                ],
                [
                    'temp_uuid' => $tempUuidG,
                    'name' => 'جرام',
                    'code' => 'g',
                    'decimal_places' => 0,
                    'is_active' => true,
                ]
            ],
            'conversions' => [
                [
                    'from_unit_temp_uuid' => $tempUuidKg,
                    'to_unit_temp_uuid' => $tempUuidG,
                    'factor' => 1000,
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/unit-groups/build', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        // التحقق من الحفظ في قاعدة البيانات
        $this->assertDatabaseHas('unit_groups', [
            'name' => 'مجموعة الوزن المخصصة',
            'company_id' => $this->company1->id,
        ]);

        $group = UnitGroup::where('name', 'مجموعة الوزن المخصصة')->first();

        $this->assertCount(2, $group->units);
        $this->assertCount(1, $group->conversions);

        $this->assertDatabaseHas('units', [
            'name' => 'كيلو جرام',
            'unit_group_id' => $group->id,
            'company_id' => $this->company1->id,
        ]);

        $this->assertDatabaseHas('units', [
            'name' => 'جرام',
            'unit_group_id' => $group->id,
            'company_id' => $this->company1->id,
        ]);
    }

    /**
     * اختبار فشل البناء عند وجود بيانات غير صالحة والتحقق من الـ Rollback.
     */
    public function test_build_rolls_back_entire_transaction_on_failure()
    {
        $this->actingAs($this->admin1);

        $tempUuidKg = (string) Str::uuid();
        $tempUuidG  = (string) Str::uuid();

        // إرسال كود فارغ للوحدة الثانية مما يتسبب في فشل الـ Validation أو الحفظ
        $payload = [
            'name' => 'مجموعة فاشلة',
            'type' => 'weight',
            'units' => [
                [
                    'temp_uuid' => $tempUuidKg,
                    'name' => 'كيلو جرام',
                    'code' => 'kg',
                    'decimal_places' => 3,
                ],
                [
                    'temp_uuid' => $tempUuidG,
                    'name' => 'جرام',
                    'code' => '', // خطأ! الرمز مطلوب
                    'decimal_places' => 0,
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/unit-groups/build', $payload);

        $response->assertStatus(422); // Validation error

        // التحقق من عدم إنشاء أي شيء في قاعدة البيانات (Rollback)
        $this->assertDatabaseMissing('unit_groups', [
            'name' => 'مجموعة فاشلة',
        ]);
    }

    /**
     * اختبار عزل بيانات الشركة (Multi-Tenant Isolation) في جلب المجموعات.
     */
    public function test_company_data_isolation()
    {
        // 1. الشركة الأولى تنشئ مجموعة
        $this->actingAs($this->admin1);
        UnitGroup::create([
            'name' => 'مجموعة الشركة الأولى',
            'type' => 'count',
            'company_id' => $this->company1->id,
        ]);

        // 2. الشركة الثانية تنشئ مجموعة
        $this->actingAs($this->admin2);
        UnitGroup::create([
            'name' => 'مجموعة الشركة الثانية',
            'type' => 'count',
            'company_id' => $this->company2->id,
        ]);

        // 3. محاولة جلب مجموعات الشركة الأولى فقط
        $this->actingAs($this->admin1);
        $response1 = $this->getJson('/api/v1/unit-groups/company');
        $response1->assertStatus(200)
            ->assertJsonMissing(['name' => 'مجموعة الشركة الثانية'])
            ->assertJsonFragment(['name' => 'مجموعة الشركة الأولى']);

        // 4. محاولة جلب مجموعات الشركة الثانية فقط
        $this->actingAs($this->admin2);
        $response2 = $this->getJson('/api/v1/unit-groups/company');
        $response2->assertStatus(200)
            ->assertJsonMissing(['name' => 'مجموعة الشركة الأولى'])
            ->assertJsonFragment(['name' => 'مجموعة الشركة الثانية']);
    }

    /**
     * اختبار جلب القوالب الجاهزة (System Groups).
     */
    public function test_can_fetch_system_templates()
    {
        $this->actingAs($this->admin1);

        $response = $this->getJson('/api/v1/unit-groups/templates');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'وحدات الوزن النظامية']);
    }
}
