<?php

namespace Tests\Feature;

// تعليق عربي: اختبارات التحقق من محرك التسعير واحتساب شرائح الخصومات والتحقق من كوبونات وأكواد الخصم التراكمية.

use App\Models\User;
use App\Models\Company;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Tests\TestCase;

class SaaSPricingTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $plan;

    protected function setUp(): void
    {
        parent::setUp();

        // إنشاء مستخدم وشركة للتحقق
        $this->user = User::factory()->create([
            'active_company_id' => 2,
        ]);

        $this->plan = Plan::create([
            'name' => 'باقة اختبار التسعير',
            'code' => 'pricing_test_plan',
            'price' => 100.00,
            'currency' => 'EGP',
            'duration' => 1,
            'duration_unit' => 'months',
            'is_active' => true,
            'features' => [],
        ]);

        // تهيئة شرائح أسعار الباقة
        DB::table('plan_pricing_tiers')->insert([
            [
                'plan_id' => $this->plan->id,
                'min_months' => 1,
                'max_months' => 2,
                'price_per_month' => 100.00,
                'discount_percent' => 0.00,
            ],
            [
                'plan_id' => $this->plan->id,
                'min_months' => 3,
                'max_months' => 5,
                'price_per_month' => 90.00,
                'discount_percent' => 10.00,
            ],
            [
                'plan_id' => $this->plan->id,
                'min_months' => 6,
                'max_months' => null,
                'price_per_month' => 80.00,
                'discount_percent' => 20.00,
            ],
        ]);

        // تهيئة الكوبونات
        DB::table('coupons')->insert([
            [
                'code' => 'SAVE20',
                'discount_type' => 'percent',
                'value' => 20.00,
                'starts_at' => Carbon::now()->subDay(),
                'ends_at' => Carbon::now()->addYear(),
                'max_uses' => 100,
                'used_count' => 0,
                'is_cumulative' => false,
                'status' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'code' => 'EXPIRED_CODE',
                'discount_type' => 'percent',
                'value' => 10.00,
                'starts_at' => Carbon::now()->subYear(),
                'ends_at' => Carbon::now()->subMonth(),
                'max_uses' => 10,
                'used_count' => 0,
                'is_cumulative' => false,
                'status' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]
        ]);
    }

    /** @test */
    public function test_pricing_calculation_without_coupon()
    {
        $this->actingAs($this->user);

        // 1. حساب التسعيرة لشهر واحد (بدون خصم)
        $response = $this->postJson('/api/v1/saas/pricing/calculate', [
            'plan_id' => $this->plan->id,
            'months' => 1
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.subtotal', 100)
            ->assertJsonPath('data.total_price', 100)
            ->assertJsonPath('data.tiered_discount_amount', 0);

        // 2. حساب التسعيرة لـ 3 أشهر (خصم 10% -> 90 EGP شهرياً)
        $response = $this->postJson('/api/v1/saas/pricing/calculate', [
            'plan_id' => $this->plan->id,
            'months' => 3
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.subtotal', 300)
            ->assertJsonPath('data.total_price', 270)
            ->assertJsonPath('data.tiered_discount_amount', 30);

        // 3. حساب التسعيرة لـ 6 أشهر (خصم 20% -> 80 EGP شهرياً)
        $response = $this->postJson('/api/v1/saas/pricing/calculate', [
            'plan_id' => $this->plan->id,
            'months' => 6
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.subtotal', 600)
            ->assertJsonPath('data.total_price', 480)
            ->assertJsonPath('data.tiered_discount_amount', 120);
    }

    /** @test */
    public function test_pricing_calculation_with_percent_coupon()
    {
        $this->actingAs($this->user);

        // حساب التسعيرة لـ 6 أشهر مع الكوبون SAVE20 (20% خصم إضافي مركب)
        // السعر بعد خصم المدة لـ 6 أشهر هو 480 EGP
        // الخصم الإضافي من الكوبون هو 480 * 20% = 96 EGP
        // الإجمالي النهائي = 480 - 96 = 384 EGP
        $response = $this->postJson('/api/v1/saas/pricing/calculate', [
            'plan_id' => $this->plan->id,
            'months' => 6,
            'coupon_code' => 'SAVE20'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.subtotal', 600)
            ->assertJsonPath('data.tiered_discount_amount', 120)
            ->assertJsonPath('data.coupon_discount_amount', 96)
            ->assertJsonPath('data.total_price', 384)
            ->assertJsonPath('data.savings', 216);
    }

    /** @test */
    public function test_coupon_validation_endpoint_success()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/v1/saas/coupons/validate', [
            'plan_id' => $this->plan->id,
            'months' => 6,
            'coupon_code' => 'SAVE20'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.code', 'SAVE20')
            ->assertJsonPath('data.discount_type', 'percent')
            ->assertJsonPath('data.value', 20);
    }

    /** @test */
    public function test_coupon_validation_endpoint_fails_when_expired()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/v1/saas/coupons/validate', [
            'plan_id' => $this->plan->id,
            'months' => 6,
            'coupon_code' => 'EXPIRED_CODE'
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'كود الخصم غير صحيح أو منتهي الصلاحية.');
    }
}
