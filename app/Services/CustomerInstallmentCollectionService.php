<?php

namespace App\Services;

use App\Models\{CashBox, Installment, InstallmentPlan, PaymentMethod};
use Illuminate\Support\Facades\{Crypt, DB};
use Illuminate\Validation\ValidationException;

/** Preview and commit share the same allocation algorithm. No writes during preview. */
class CustomerInstallmentCollectionService
{
    public function plans(int $customerId)
    {
        $user = auth()->user();
        abort_unless($user->hasAnyPermission([perm_key('admin.super'), perm_key('installment_payments.create')]), 403);
        $companyId = app(CurrentCompanyResolver::class)->resolve();
        abort_unless($companyId, 403, 'اختر الشركة أولاً.');
        $query = InstallmentPlan::query()->where('company_id', $companyId)->where('user_id', $customerId)->where('status', '!=', 'canceled');
        if (!$user->hasAnyPermission([perm_key('admin.super'), perm_key('admin.company'), perm_key('installment_plans.view_all')])) {
            if ($user->hasPermissionTo(perm_key('installment_plans.view_children'))) {
                $query->whereCreatedByUserOrChildren();
            } elseif ($user->hasPermissionTo(perm_key('installment_plans.view_self'))) {
                $query->whereCreatedByUser();
            } else {
                abort(403);
            }
        }
        return $query;
    }

    public function candidates(int $customerId, bool $lock = false): array
    {
        $plansQuery = $this->plans($customerId)->with('invoice:id,invoice_number')->orderBy('id');
        $plans = ($lock ? $plansQuery->lockForUpdate() : $plansQuery)->get();
        $query = Installment::query()->whereIn('installment_plan_id', $plans->pluck('id'))
            ->where('status', '!=', 'canceled')->where('remaining', '>', 0)
            ->orderBy('due_date')->orderBy('installment_plan_id')->orderBy('id');
        $rows = ($lock ? $query->lockForUpdate() : $query)->get();
        $cutoff = now()->endOfMonth()->toDateString();
        $today = now()->toDateString();
        $items = $rows->map(function ($row) use ($plans, $cutoff, $today) {
            $plan = $plans->firstWhere('id', $row->installment_plan_id);
            return [
                'id' => $row->id, 'installment_plan_id' => $plan->id, 'installment_number' => $row->installment_number,
                'invoice_number' => $plan->invoice?->invoice_number,
                'due_date' => $row->due_date->toDateString(),
                'remaining' => bcadd((string) $row->remaining, '0', 2),
                'selected' => $row->due_date->toDateString() <= $cutoff,
                'overdue' => $row->due_date->toDateString() < $today,
            ];
        })->all();
        return ['customer_id' => $customerId, 'cutoff' => $cutoff, 'items' => $items,
            'due_total' => $this->sum(array_filter($items, fn ($i) => $i['selected']), 'remaining'),
            'total_remaining' => $this->sum($items, 'remaining')];
    }

    private function sum(array $rows, string $key): string
    {
        return array_reduce($rows, fn ($sum, $row) => bcadd($sum, (string) $row[$key], 2), '0.00');
    }

    public function preview(array $input, bool $lock = false): array
    {
        $data = $this->candidates((int) $input['customer_id'], $lock);
        $ids = array_map('intval', $input['installment_ids']);
        $selected = array_values(array_filter($data['items'], fn ($row) => in_array($row['id'], $ids, true)));
        if (count($selected) !== count($ids)) {
            throw ValidationException::withMessages(['installment_ids' => 'بعض الأقساط غير متاحة. حدّث القائمة.']);
        }
        $left = bcadd((string) $input['amount'], '0', 2);
        $selectedTotal = $this->sum($selected, 'remaining');
        $allocations = [];
        $manual = array_key_exists('allocations', $input) ? collect($input['allocations'])->keyBy('installment_id') : null;
        if ($manual && ($manual->count() !== count($input['allocations']) || $manual->keys()->diff($ids)->isNotEmpty())) {
            throw ValidationException::withMessages(['allocations' => 'توزيع غير صالح.']);
        }
        foreach ($selected as $row) {
            $value = $manual ? (string) ($manual->get($row['id'])['amount'] ?? '0') : (bccomp($left, $row['remaining'], 2) < 0 ? $left : $row['remaining']);
            if (bccomp($value, '0', 2) < 0 || bccomp($value, $row['remaining'], 2) > 0) {
                throw ValidationException::withMessages(['allocations' => 'المبلغ المخصص يتجاوز المتبقي من القسط.']);
            }
            $left = bcsub($left, $value, 2);
            if (bccomp($value, '0', 2) > 0) {
                $allocations[] = $row + ['amount' => bcadd($value, '0', 2), 'remaining_after' => bcsub($row['remaining'], $value, 2)];
            }
        }
        if (bccomp($left, '0', 2) !== 0) {
            throw ValidationException::withMessages(['amount' => $manual ? 'مجموع التوزيع يجب أن يساوي المبلغ المستلم.' : 'المبلغ أكبر من إجمالي الأقساط المحددة. اختر أقساطًا قادمة أو عدّل المبلغ.']);
        }
        if (empty($allocations)) {
            throw ValidationException::withMessages(['amount' => 'حدد أقساطًا وأدخل مبلغًا أكبر من صفر.']);
        }
        $this->validatePaymentOptions($input);
        $result = ['allocations' => $allocations, 'amount' => bcadd((string) $input['amount'], '0', 2),
            'selected_total' => $selectedTotal, 'due_total' => $data['due_total'], 'cutoff' => $data['cutoff'],
            'shortfall' => bcsub($selectedTotal, (string) $input['amount'], 2),
            'advance_amount' => $this->sum(array_values(array_filter($allocations, fn ($r) => !$r['selected'])), 'amount')];
        // Include the complete visible schedule to detect concurrent changes and month rollover.
        $duePaid = $this->sum(array_values(array_filter($allocations, fn ($r) => $r['selected'])), 'amount');
        $result['remaining_due'] = bcsub($data['due_total'], $duePaid, 2);
        $result['total_remaining_after'] = bcsub($data['total_remaining'], (string) $input['amount'], 2);
        $result['revision'] = hash('sha256', json_encode($data));
        return $result;
    }

    private function validatePaymentOptions(array $input): void
    {
        $companyId = app(CurrentCompanyResolver::class)->resolve();
        abort_if(config('app.active_branch_id') === 'all', 422, 'اختر فرعًا محددًا للتحصيل.');
        $box = CashBox::query()->where('company_id', $companyId)->where('status', \App\Enums\CashBoxStatus::ACTIVE)->find($input['cash_box_id']);
        abort_unless($box, 403, 'الخزنة غير متاحة.');
        $employee = auth()->user();
        if (!$employee->hasAnyPermission([perm_key('admin.super'), perm_key('admin.company'), perm_key('cash_boxes.view_all')])) {
            abort_unless($box->user_id == $employee->id || $box->created_by == $employee->id
                || (!$box->user_id && $box->users()->where('users.id', $employee->id)->exists()), 403, 'الخزنة غير متاحة لهذا الموظف.');
        }
        abort_unless(PaymentMethod::query()->where('company_id', $companyId)->where('active', true)->find($input['payment_method_id']), 403, 'طريقة الدفع غير متاحة.');
    }

    public function issuePreview(array $input): array
    {
        $preview = $this->preview($input);
        $preview['token'] = Crypt::encryptString(json_encode([
            'input' => $input, 'preview' => $preview, 'employee_id' => auth()->id(),
            'company_id' => app(CurrentCompanyResolver::class)->resolve(),
            'branch_id' => config('app.active_branch_id'), 'expires_at' => now()->addMinutes(15)->timestamp,
        ]));
        return $preview;
    }

    public function commit(string $token, string $key): array
    {
        try { $signed = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { throw ValidationException::withMessages(['token' => 'المعاينة غير صالحة. أعد المعاينة.']); }
        abort_unless($signed['employee_id'] === auth()->id()
            && $signed['company_id'] === app(CurrentCompanyResolver::class)->resolve()
            && $signed['branch_id'] === config('app.active_branch_id'), 403);
        // Authorize even an idempotent retry; never return another employee's receipt.
        $this->plans((int) $signed['input']['customer_id']);
        $hash = hash('sha256', $token);
        $companyId = $signed['company_id'];
        $existing = DB::table('installment_collections')->where('company_id', $companyId)->where('request_key', $key)->first();
        if ($existing) {
            abort_unless($existing->created_by == auth()->id() && hash_equals($existing->request_hash, $hash), 409, 'رقم الطلب مستخدم لعملية أخرى.');
            return json_decode($existing->receipt, true);
        }
        abort_if($signed['expires_at'] < now()->timestamp, 409, 'انتهت المعاينة. أعد مراجعة التوزيع.');
        try {
            return DB::transaction(function () use ($signed, $hash, $companyId, $key) {
                $collectionId = DB::table('installment_collections')->insertGetId([
                    'company_id' => $companyId, 'created_by' => auth()->id(), 'request_key' => $key,
                    'request_hash' => $hash, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $fresh = $this->preview($signed['input'], true);
                abort_unless($fresh === $signed['preview'], 409, 'تغيرت الأقساط منذ المعاينة. حدّث التوزيع قبل الدفع.');
                $payments = [];
                foreach (collect($fresh['allocations'])->groupBy('installment_plan_id')->sortKeys() as $planId => $rows) {
                    $allocations = $rows->mapWithKeys(fn ($row) => [$row['id'] => $row['amount']])->all();
                    $amount = $this->sum($rows->all(), 'amount');
                    $result = app(InstallmentPaymentService::class)->payExactInstallments(array_keys($allocations), $amount,
                        array_merge($signed['input'], ['installment_plan_id' => $planId, 'exact_allocations' => $allocations]));
                    $payments[] = ['installment_plan_id' => $planId, 'payment_id' => $result['installmentPayment']->id, 'amount' => $amount];
                }
                $receipt = $fresh + ['collection_id' => $collectionId, 'customer_id' => $signed['input']['customer_id'],
                    'payments' => $payments, 'paid_at' => $signed['input']['paid_at']];
                DB::table('installment_collections')->where('id', $collectionId)->update(['receipt' => json_encode($receipt)]);
                return $receipt;
            }, 3);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $existing = DB::table('installment_collections')->where('company_id', $companyId)->where('request_key', $key)->first();
            abort_unless($existing && $existing->created_by == auth()->id() && hash_equals($existing->request_hash, $hash), 409);
            return json_decode($existing->receipt, true);
        }
    }
}
