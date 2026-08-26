<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use App\Services\FinancialEngine;
use Modules\Accounting\Models\Custody;
use Modules\Companies\Models\StakeholderFinancialBalance;
use Illuminate\Support\Str;
use App\Models\User;

class CustodyService
{
    protected FinancialEngine $engine;

    public function __construct(FinancialEngine $engine)
    {
        $this->engine = $engine;
    }

    public function issueCustody(array $data)
    {
        return DB::transaction(function () use ($data) {
            $custody = Custody::create([
                'company_id' => $data['company_id'],
                'user_id' => $data['user_id'],
                'cashbox_id' => $data['cashbox_id'],
                'amount' => $data['amount'],
                'issue_date' => $data['issue_date'],
                'description' => $data['description'] ?? null,
                'created_by' => $data['created_by'],
                'status' => 'open',
                'settled_cash_amount' => 0,
                'settled_expenses_amount' => 0,
            ]);

            $operationId = (string) Str::uuid();

            $op = \App\Models\FinancialOperation::create([
                'id' => $operationId,
                'company_id' => $data['company_id'],
                'type' => 'custody_issue',
                'status' => 'active',
                'amount' => $data['amount'],
                'source_type' => Custody::class,
                'source_id' => $custody->id,
                'created_by' => $data['created_by'],
            ]);

            $this->engine->payMoney(
                $data['amount'],
                $data['cashbox_id'],
                $operationId,
                ['description' => $data['description'] ?? 'Issue Custody']
            );

            // Update Stakeholder Balance
            $employee = \App\Models\User::withoutGlobalScopes()->find($data['user_id']);
            app(\App\Services\CustodyBalanceService::class)->add($employee, (float)$data['amount'], $operationId, ['company_id' => $data['company_id'], 'description' => 'إصدار عهدة للموظف']);

            // Ledger logic
            $this->createLedgerEntry($operationId, $data['amount'], 'debit', 'custody_issue', $custody);

            return $custody;
        });
    }

    public function refund(Custody $custody, array $data)
    {
        return DB::transaction(function () use ($custody, $data) {
            $outstanding = $custody->amount - $custody->settled_cash_amount - $custody->settled_expenses_amount;
            if ($data['amount'] > $outstanding) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'Refund amount exceeds outstanding custody balance'
                ]);
            }

            $custody->settled_cash_amount += $data['amount'];
            if ($custody->settled_cash_amount + $custody->settled_expenses_amount == $custody->amount) {
                $custody->status = 'closed';
            }
            $custody->save();

            $operationId = (string) Str::uuid();

            $op = \App\Models\FinancialOperation::create([
                'id' => $operationId,
                'company_id' => $custody->company_id,
                'type' => 'custody_refund',
                'status' => 'active',
                'amount' => $data['amount'],
                'source_type' => Custody::class,
                'source_id' => $custody->id,
                'created_by' => auth()->id() ?? $custody->created_by,
            ]);

            $this->engine->receiveMoney(
                $data['amount'],
                $data['cashbox_id'],
                $operationId,
                ['description' => $data['description'] ?? 'Refund Custody']
            );

            // Decrease Balance
            $employee = \App\Models\User::withoutGlobalScopes()->find($custody->user_id);
            app(\App\Services\CustodyBalanceService::class)->reduce($employee, (float)$data['amount'], $operationId, ['company_id' => $custody->company_id, 'description' => 'استرداد نقدي من عهدة الموظف']);

            // Ledger logic
            $this->createLedgerEntry($operationId, $data['amount'], 'credit', 'custody_refund', $custody);

            return $custody;
        });
    }

    public function processExpense(Custody $custody, $amount, string $operationId)
    {
        // This is called from ExpenseService when an expense is linked to a custody
        return DB::transaction(function () use ($custody, $amount, $operationId) {
            $outstanding = $custody->amount - $custody->settled_cash_amount - $custody->settled_expenses_amount;
            if ($amount > $outstanding) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'Expense amount exceeds outstanding custody balance'
                ]);
            }

            $custody->settled_expenses_amount += $amount;
            if ($custody->settled_cash_amount + $custody->settled_expenses_amount == $custody->amount) {
                $custody->status = 'closed';
            }
            $custody->save();

            $employee = \App\Models\User::withoutGlobalScopes()->find($custody->user_id);
            app(\App\Services\CustodyBalanceService::class)->reduce($employee, (float)$amount, $operationId, ['company_id' => $custody->company_id, 'description' => 'تسوية مصروفات من العهدة']);
        });
    }

    private function updateBalance($companyId, $userId, $amount)
    {
        StakeholderFinancialBalance::firstOrCreate([
            'company_id' => $companyId,
            'user_id' => $userId,
            'relation_type' => 'custody',
        ], ['balance' => 0.00]);

        $balance = StakeholderFinancialBalance::where([
            'company_id' => $companyId,
            'user_id' => $userId,
            'relation_type' => 'custody',
        ])->lockForUpdate()->firstOrFail();

        $balance->balance += $amount;
        $balance->save();
    }

    private function createLedgerEntry($operationId, $amount, $type, $desc, $source)
    {
        \Modules\Accounting\Models\FinancialLedger::create([
            'financial_operation_id' => $operationId,
            'company_id' => $source->company_id,
            'source_type' => get_class($source),
            'source_id' => $source->id,
            'account_type' => 'asset',
            'type' => $type,
            'amount' => $amount,
            'entry_date' => now(),
            'description' => $desc,
            'created_by' => auth()->id() ?? $source->created_by,
        ]);
    }
}
