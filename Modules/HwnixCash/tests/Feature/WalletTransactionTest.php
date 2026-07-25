<?php
// اختبارات الميزات المتكاملة لحركات المحافظ الإلكترونية وحساب الحدود الديناميكية بكاش هونكس.

namespace Modules\HwnixCash\tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\HwnixCash\Models\HwnixCashDevice;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Models\HwnixCashWalletTransaction;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WalletTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected HwnixCashDevice $device;
    protected HwnixCashLine $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->company = Company::create([
            'id' => 1,
            'name' => 'شركة المحافظ كاش هونكس',
            'email' => 'wallet@example.com',
            'phone' => '0599000000',
        ]);

        $this->user = User::create([
            'company_id' => $this->company->id,
            'full_name' => 'مدير المحافظ',
            'nickname' => 'أبو المحافظ',
            'phone' => '0588888888',
            'password' => bcrypt('password'),
        ]);

        if (config('permission.teams')) {
            setPermissionsTeamId($this->company->id);
        }
        $this->user->givePermissionTo(perm_key('admin.super'));

        $this->device = HwnixCashDevice::create([
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
            'android_id' => 'android-wallet-test',
            'uuid' => 'uuid-wallet-test',
            'device_name' => 'Wallet Master Phone',
            'brand' => 'Samsung',
            'model' => 'S21',
            'android_version' => '13',
            'app_version' => '1.0.0',
            'status' => 'active',
        ]);

        $this->line = HwnixCashLine::create([
            'device_android_id' => $this->device->android_id,
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
            'slot_index' => 0,
            'subscription_id' => 'sub-wallet-1',
            'carrier' => 'Vodafone',
            'phone_number' => '01012345678',
            'status' => 'active',
            'daily_withdraw_limit' => 10000.00,
            'daily_deposit_limit' => 20000.00,
            'monthly_withdraw_limit' => 50000.00,
            'monthly_deposit_limit' => 100000.00,
        ]);

        Sanctum::actingAs($this->user);
    }

    protected function seedPermissions(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        if (config('permission.teams')) {
            setPermissionsTeamId(1);
        }
        foreach (config('permissions_keys', []) as $entity => $actions) {
            foreach ($actions as $key => $actionData) {
                if ($key === 'name') continue;
                if (isset($actionData['key'])) {
                    Permission::firstOrCreate(
                        ['name' => $actionData['key']],
                        ['guard_name' => 'web']
                    );
                }
            }
        }
    }

    /**
     * اختبار إنشاء معاملة محفظة جديدة عبر الـ API وتخزين البيانات الخام والمصدر.
     */
    public function test_can_create_wallet_transaction(): void
    {
        $payload = [
            'line_id' => $this->line->id,
            'operation_type' => 'cash_withdraw',
            'provider' => 'vodafone_cash',
            'status' => 'success',
            'source' => 'sms',
            'amount' => 1500.00,
            'fee' => 15.00,
            'balance_after' => 8500.00,
            'currency' => 'EGP',
            'operation_number' => 'OP-99887766',
            'operation_at' => now()->toIso8601String(),
            'target_phone' => '01000000000',
            'raw_sms' => 'تم سحب مبلغ 1500 جنيه من فودافون كاش بنجاح رقم العملية 99887766',
            'metadata' => ['parser' => 'v1.2', 'device_id' => $this->device->id]
        ];

        $response = $this->postJson('/api/v1/hwnix-cash/wallet-transactions', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.amount', 1500)
            ->assertJsonPath('data.currency', 'EGP')
            ->assertJsonPath('data.provider', 'vodafone_cash')
            ->assertJsonPath('data.source', 'sms');

        $this->assertDatabaseHas('hwnix_cash_wallet_transactions', [
            'line_id' => $this->line->id,
            'operation_number' => 'OP-99887766',
            'raw_sms' => 'تم سحب مبلغ 1500 جنيه من فودافون كاش بنجاح رقم العملية 99887766'
        ]);
    }

    /**
     * اختبار حساب المستهلكات والمتبقيات ديناميكياً داخل LineResource دون تخزين مادي في جدول الخطوط.
     */
    public function test_dynamic_limit_calculations_in_line_resource(): void
    {
        // 1. إضافة سحب
        HwnixCashWalletTransaction::create([
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
            'line_id' => $this->line->id,
            'operation_type' => 'cash_withdraw',
            'provider' => 'vodafone_cash',
            'status' => 'success',
            'source' => 'sms',
            'amount' => 3000.00,
            'currency' => 'EGP',
            'operation_at' => now(),
            'raw_sms' => 'تم سحب 3000'
        ]);

        // 2. إضافة إيداع
        HwnixCashWalletTransaction::create([
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
            'line_id' => $this->line->id,
            'operation_type' => 'cash_deposit',
            'provider' => 'vodafone_cash',
            'status' => 'success',
            'source' => 'sms',
            'amount' => 5000.00,
            'currency' => 'EGP',
            'operation_at' => now(),
            'raw_sms' => 'تم إيداع 5000'
        ]);

        $response = $this->getJson('/api/v1/hwnix-cash/lines');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.0.daily_withdraw_used', 3000)
            ->assertJsonPath('data.0.daily_deposit_used', 5000)
            ->assertJsonPath('data.0.daily_withdraw_remaining', 7000) // 10000 - 3000
            ->assertJsonPath('data.0.daily_deposit_remaining', 15000); // 20000 - 5000
    }
}
