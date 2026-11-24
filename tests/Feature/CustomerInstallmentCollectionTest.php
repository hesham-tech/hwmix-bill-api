<?php

namespace Tests\Feature;

use App\Models\{User, Company, CashBox, PaymentMethod, Installment, InstallmentPlan};
use App\Services\{CustomerInstallmentCollectionService, InstallmentPaymentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerInstallmentCollectionTest extends TestCase
{
    use RefreshDatabase;
    private User $employee;
    private User $customer;
    private Company $company;
    private CashBox $box;
    private PaymentMethod $method;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Mail::fake();
        \Illuminate\Support\Facades\Notification::fake();
        $this->seed(\Database\Seeders\AddPermissionsSeeder::class);
        $this->travelTo(now()->setDate(2026, 11, 20)->startOfDay());
        $this->employee = User::factory()->create();
        $this->company = Company::factory()->create(['created_by' => $this->employee->id]);
        $this->employee->update(['company_id' => $this->company->id, 'active_company_id' => $this->company->id]);
        $this->employee->companies()->syncWithoutDetaching([$this->company->id => ['status' => 'active']]);
        setPermissionsTeamId($this->company->id);
        $this->employee->givePermissionTo(Permission::firstOrCreate(['name' => 'admin.super', 'guard_name' => 'web']));
        $this->actingAs($this->employee, 'sanctum');
        $this->customer = User::factory()->create(['company_id' => $this->company->id]);
        $this->box = CashBox::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->employee->id, 'is_active' => true]);
        $this->method = PaymentMethod::factory()->create(['company_id' => $this->company->id, 'active' => true]);
    }

    private function installment(string $due, string $amount, ?InstallmentPlan $plan = null): Installment
    {
        $plan ??= InstallmentPlan::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->customer->id, 'created_by' => $this->employee->id]);
        return Installment::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->customer->id,
            'created_by' => $this->employee->id, 'installment_plan_id' => $plan->id, 'invoice_id' => $plan->invoice_id,
            'due_date' => $due, 'amount' => $amount, 'remaining' => $amount, 'status' => 'pending']);
    }

    private function input(array $rows, string $amount): array
    {
        return ['customer_id' => $this->customer->id, 'installment_ids' => array_map(fn ($r) => $r->id, $rows),
            'amount' => $amount, 'cash_box_id' => $this->box->id, 'payment_method_id' => $this->method->id, 'paid_at' => '2026-11-20'];
    }

    public function test_candidates_include_arrears_and_rest_of_month_but_do_not_select_next_month(): void
    {
        $this->installment('2026-10-05', '200.00');
        $this->installment('2026-11-30', '500.00');
        $this->installment('2026-12-01', '300.00');
        $this->getJson('/api/v1/installment-collections/candidates?customer_id='.$this->customer->id)->assertOk()
            ->assertJsonPath('data.due_total', '700.00')->assertJsonPath('data.total_remaining', '1000.00')
            ->assertJsonPath('data.items.1.selected', true)->assertJsonPath('data.items.2.selected', false);
        $this->assertDatabaseCount('installment_collections', 0);
    }

    public function test_partial_preview_orders_by_due_date_across_plans_and_has_no_writes(): void
    {
        $new = $this->installment('2026-11-30', '500.00');
        $old = $this->installment('2026-10-01', '200.00');
        $this->postJson('/api/v1/installment-collections/preview', $this->input([$new, $old], '300.00'))->assertOk()
            ->assertJsonPath('data.allocations.0.id', $old->id)->assertJsonPath('data.allocations.0.amount', '200.00')
            ->assertJsonPath('data.allocations.1.amount', '100.00')->assertJsonPath('data.allocations.1.remaining_after', '400.00');
        $this->assertDatabaseCount('installment_payments', 0);
        $this->assertEquals('500', $new->fresh()->remaining);
    }

    public function test_surplus_requires_explicit_future_selection_and_reports_advance_amount(): void
    {
        $now = $this->installment('2026-11-25', '200.00');
        $future = $this->installment('2026-12-25', '300.00');
        $this->postJson('/api/v1/installment-collections/preview', $this->input([$now], '250.00'))->assertUnprocessable();
        $this->postJson('/api/v1/installment-collections/preview', $this->input([$now,$future], '250.00'))->assertOk()
            ->assertJsonPath('data.advance_amount', '50.00')->assertJsonPath('data.remaining_due', '0.00');
    }

    public function test_manual_distribution_is_exact_and_validated(): void
    {
        $a = $this->installment('2026-10-01', '200.00');
        $b = $this->installment('2026-11-01', '500.00');
        $input = $this->input([$a,$b], '100.01');
        $input['allocations'] = [['installment_id' => $b->id, 'amount' => '100.01']];
        $this->postJson('/api/v1/installment-collections/preview', $input)->assertOk()
            ->assertJsonPath('data.allocations.0.id', $b->id)->assertJsonPath('data.allocations.0.remaining_after', '399.99');
        $input['amount'] = '101.00';
        $this->postJson('/api/v1/installment-collections/preview', $input)->assertUnprocessable();
    }

    public function test_stale_preview_rejects_whole_collection(): void
    {
        $a = $this->installment('2026-11-01', '200.00');
        $preview = app(CustomerInstallmentCollectionService::class)->issuePreview($this->input([$a], '100.00'));
        $a->update(['remaining' => '150.00']);
        $this->postJson('/api/v1/installment-collections', ['token' => $preview['token'], 'request_key' => Str::uuid()->toString()])->assertConflict();
        $this->assertDatabaseCount('installment_collections', 0);
        $this->assertDatabaseCount('installment_payments', 0);
    }

    public function test_foreign_customer_installments_and_foreign_cashbox_are_rejected(): void
    {
        $a = $this->installment('2026-11-01', '200.00');
        $input = $this->input([$a], '100.00');
        $input['customer_id'] = User::factory()->create()->id;
        $this->postJson('/api/v1/installment-collections/preview', $input)->assertUnprocessable();
        $input = $this->input([$a], '100.00');
        $input['cash_box_id'] = CashBox::factory()->create()->id;
        $this->postJson('/api/v1/installment-collections/preview', $input)->assertForbidden();
    }

    public function test_commit_records_each_plan_once_and_retry_returns_same_receipt(): void
    {
        $a = $this->installment('2026-10-01', '200.00');
        $b = $this->installment('2026-11-01', '500.00');
        // Exercise actual allocation and DB writes; stub only the external financial posting boundary.
        $this->mock(\App\Contracts\FinancialEngineInterface::class)->shouldReceive('processPaymentReceipt')->twice()->andReturn('test-operation');
        $service = app(CustomerInstallmentCollectionService::class);
        $preview = $service->issuePreview($this->input([$a,$b], '300.00'));
        $key = Str::uuid()->toString();
        $first = $service->commit($preview['token'], $key);
        $second = $service->commit($preview['token'], $key);
        $this->assertSame($first, $second);
        $this->assertDatabaseCount('installment_collections', 1);
        $this->assertDatabaseCount('installment_payments', 2);
        $this->assertEquals(0, $a->fresh()->remaining);
        $this->assertEquals(400, $b->fresh()->remaining);
        $this->assertEquals(400, $b->installmentPlan->fresh()->remaining_amount);
    }

    public function test_failure_on_second_plan_rolls_back_first_plan_and_receipt(): void
    {
        $a = $this->installment('2026-10-01', '200.00');
        $b = $this->installment('2026-11-01', '500.00');
        $calls = 0;
        $this->mock(\App\Contracts\FinancialEngineInterface::class)->shouldReceive('processPaymentReceipt')->andReturnUsing(function () use (&$calls) {
            if (++$calls === 2) throw new \RuntimeException('test posting failure');
            return 'test-operation';
        });
        $service = app(CustomerInstallmentCollectionService::class);
        $preview = $service->issuePreview($this->input([$a,$b], '300.00'));
        try { $service->commit($preview['token'], Str::uuid()->toString()); $this->fail('Expected posting failure'); }
        catch (\RuntimeException $e) { $this->assertSame('test posting failure', $e->getMessage()); }
        $this->assertEquals(200, $a->fresh()->remaining);
        $this->assertEquals(500, $b->fresh()->remaining);
        $this->assertDatabaseCount('installment_payments', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('installment_collections', 0);
    }

    public function test_invalid_amounts_duplicate_ids_and_overallocation_are_rejected(): void
    {
        $a = $this->installment('2026-11-01', '200.00');
        foreach (['0', '-1', '0.001', '201.00'] as $amount) {
            $this->postJson('/api/v1/installment-collections/preview', $this->input([$a], $amount))->assertUnprocessable();
        }
        $this->postJson('/api/v1/installment-collections/preview', $this->input([$a,$a], '100'))->assertUnprocessable();
        $this->assertDatabaseCount('installment_payments', 0);
    }

    public function test_expired_preview_and_month_rollover_require_new_preview(): void
    {
        $a = $this->installment('2026-11-01', '200.00');
        $preview = app(CustomerInstallmentCollectionService::class)->issuePreview($this->input([$a], '100.00'));
        $this->travel(16)->minutes();
        $this->postJson('/api/v1/installment-collections', ['token'=>$preview['token'],'request_key'=>(string) Str::uuid()])->assertConflict();
        $this->travelTo(now()->setDate(2026, 11, 30)->endOfDay()->subMinutes(1));
        $preview = app(CustomerInstallmentCollectionService::class)->issuePreview($this->input([$a], '100.00'));
        $this->travel(2)->minutes();
        $this->postJson('/api/v1/installment-collections', ['token'=>$preview['token'],'request_key'=>(string) Str::uuid()])->assertConflict();
        $this->assertDatabaseCount('installment_collections', 0);
    }

    public function test_employee_without_collection_permission_is_forbidden(): void
    {
        $staff = User::factory()->create(['company_id'=>$this->company->id,'active_company_id'=>$this->company->id]);
        $this->actingAs($staff, 'sanctum');
        $this->getJson('/api/v1/installment-collections/candidates?customer_id='.$this->customer->id)->assertForbidden();
    }

    public function test_partial_remaining_is_used_and_canceled_installments_are_excluded(): void
    {
        $a = $this->installment('2026-11-01', '200.00');
        $a->update(['remaining'=>'60.50','status'=>'partially_paid']);
        $b = $this->installment('2026-11-01', '500.00');
        $b->update(['status'=>'canceled']);
        $data = app(CustomerInstallmentCollectionService::class)->candidates($this->customer->id);
        $this->assertSame('60.50', $data['due_total']);
        $this->assertCount(1, $data['items']);
    }

    public function test_real_financial_posting_increases_cashbox_by_collection_total_once(): void
    {
        $a = $this->installment('2026-10-01', '200.00');
        $b = $this->installment('2026-11-01', '500.00');
        foreach ([$a,$b] as $row) {
            $row->installmentPlan->invoice->update(['company_id'=>$this->company->id, 'user_id'=>$this->customer->id,
                'created_by'=>$this->employee->id, 'is_marketplace_managed'=>false]);
        }
        $before = $this->box->fresh()->balance;
        $service = app(CustomerInstallmentCollectionService::class);
        $preview = $service->issuePreview($this->input([$a,$b], '300.00'));
        $key = (string) Str::uuid();
        $this->postJson('/api/v1/installment-collections', ['token'=>$preview['token'],'request_key'=>$key])->assertOk();
        $this->postJson('/api/v1/installment-collections', ['token'=>$preview['token'],'request_key'=>$key])->assertOk();
        $this->assertEquals(300, $this->box->fresh()->balance - $before);
        $this->assertDatabaseCount('installment_collections', 1);
    }

    public function test_tampered_preview_is_rejected_without_financial_writes(): void
    {
        $a = $this->installment('2026-11-01', '200.00');
        $token = app(CustomerInstallmentCollectionService::class)->issuePreview($this->input([$a], '100.00'))['token'];
        $this->postJson('/api/v1/installment-collections', ['token'=>'invalid-'.$token,'request_key'=>(string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('installment_payments', 0);
        $this->assertDatabaseCount('installment_collections', 0);
    }

    public function test_another_employee_cannot_use_an_issued_preview(): void
    {
        $a = $this->installment('2026-11-01', '200.00');
        $token = app(CustomerInstallmentCollectionService::class)->issuePreview($this->input([$a], '100.00'))['token'];
        $other = User::factory()->create(['company_id'=>$this->company->id,'active_company_id'=>$this->company->id]);
        $this->actingAs($other, 'sanctum');
        $this->postJson('/api/v1/installment-collections', ['token'=>$token,'request_key'=>(string) Str::uuid()])->assertForbidden();
        $this->assertDatabaseCount('installment_payments', 0);
    }

    public function test_request_key_cannot_be_reused_with_a_different_preview(): void
    {
        $a = $this->installment('2026-11-01', '200.00');
        $this->mock(\App\Contracts\FinancialEngineInterface::class)->shouldReceive('processPaymentReceipt')->once()->andReturn('test-operation');
        $service = app(CustomerInstallmentCollectionService::class);
        $key = (string) Str::uuid();
        $first = $service->issuePreview($this->input([$a], '50.00'));
        $service->commit($first['token'], $key);
        $second = $service->issuePreview($this->input([$a], '60.00'));
        $this->postJson('/api/v1/installment-collections', ['token'=>$second['token'],'request_key'=>$key])->assertConflict();
        $this->assertDatabaseCount('installment_payments', 1);
        $this->assertEquals('150.00', $a->fresh()->remaining);
    }

    public function test_decimal_allocations_reach_each_plan_without_a_float_conversion(): void
    {
        $a = $this->installment('2026-10-01', '0.10');
        $b = $this->installment('2026-11-01', '0.20');
        $this->mock(\App\Contracts\FinancialEngineInterface::class)->shouldReceive('processPaymentReceipt')->twice()->andReturn('test-operation');
        $service = app(CustomerInstallmentCollectionService::class);
        $preview = $service->issuePreview($this->input([$a,$b], '0.30'));
        $receipt = $service->commit($preview['token'], (string) Str::uuid());
        $this->assertSame('0.30', $receipt['amount']);
        $this->assertSame(['0.10','0.20'], array_column($receipt['payments'], 'amount'));
        $this->assertEquals('0.00', $a->fresh()->remaining);
        $this->assertEquals('0.00', $b->fresh()->remaining);
    }
}
