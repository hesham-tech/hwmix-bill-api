<?php

namespace App\Services;

use App\Models\PartnerOperation;
use App\Models\User;
use App\Contracts\FinancialEngineInterface;
use App\Models\FinancialOperation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Str;

/**
 * ???? ????? ?????? ??????? (????? ??? ???? ???? ????)
 */
class PartnerOperationService
{
    protected FinancialEngineInterface $engine;
    protected FinancialLedgerService $ledgerService;

    public function __construct(FinancialEngineInterface $engine, FinancialLedgerService $ledgerService)
    {
        $this->engine = $engine;
        $this->ledgerService = $ledgerService;
    }

    public const DEPOSIT_TYPES = [
        'capital_increase',
        'partner_loan_given',
        'loss_coverage',
    ];

    public const WITHDRAW_TYPES = [
        'capital_withdrawal',
        'partner_loan_repaid',
        'profit_distribution',
    ];

    /**
     * ????? ??????? ??????? ?????? ?????? ??????? ??????? ???????
     */
    public function executeOperation(array $data): PartnerOperation
    {
        return DB::transaction(function () use ($data) {
            $companyId = Auth::user()->active_company_id ?? null;
            if (!$companyId) {
                throw new Exception('?? ???? ????? ?????? ?????? ????????.');
            }

            $type = $data['type'];
            $amount = (float) $data['amount'];
            $cashBoxId = (int) $data['cashbox_id'];
            $partnerId = (int) $data['partner_id'];
            $operationDate = isset($data['operation_date']) ? $data['operation_date'] : now();

            $partnerOperation = PartnerOperation::create([
                'company_id' => $companyId,
                'partner_id' => $partnerId,
                'cashbox_id' => $cashBoxId,
                'type' => $type,
                'amount' => $amount,
                'operation_date' => $operationDate,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
                'status' => 'completed'
            ]);

            $operationId = (string) Str::uuid();

            // 1. Create FinancialOperation manually (as FinancialEngine relies on it for reversal)
            FinancialOperation::create([
                'id' => $operationId,
                'company_id' => $companyId,
                'type' => 'partner_operation',
                'amount' => $amount,
                'source_type' => get_class($partnerOperation),
                'source_id' => $partnerOperation->id,
                'status' => 'completed',
                'metadata' => json_encode(['partner_id' => $partnerId]),
            ]);
            
            $partnerOperation->financial_operation_id = $operationId;
            $partnerOperation->save();

            $partnerName = $partnerOperation->partner?->nickname ?? $partnerOperation->partner?->name ?? "???? #".$partnerId;
            $notes = $partnerOperation->notes ? " - ".$partnerOperation->notes : "";
            $desc = "????? ????? ($type) - ??????: $partnerName$notes";

            // 2. ?????? ??????? ??? ?????? ??????
            $isDeposit = in_array($type, self::DEPOSIT_TYPES);
            if ($isDeposit) {
                $this->engine->receiveMoney($amount, $cashBoxId, $operationId, [
                    'description' => $desc,
                    'user_id' => $partnerId
                ]);
            } else {
                $this->engine->payMoney($amount, $cashBoxId, $operationId, [
                    'description' => $desc,
                    'user_id' => $partnerId
                ]);
            }

            // 3. ????? ?????? ???????? (Assets + Equity/Liability) ?? ???? ?????? ??????? ???? ???? ??????? ???????
            $this->ledgerService->recordPartnerOperation($partnerOperation);
            
            // update the financial_operation_id for those ledgers to map them to the unified engine
            \App\Models\FinancialLedger::withoutGlobalScopes()
                ->where('source_type', get_class($partnerOperation))
                ->where('source_id', $partnerOperation->id)
                ->whereNull('financial_operation_id')
                ->update(['financial_operation_id' => $operationId]);

            return $partnerOperation->load(['partner', 'cashBox']);
        });
    }
    
    /**
     * ??? (Reversal) ?????? ??????
     */
    public function reverseOperation(PartnerOperation $operation, int $userId): void
    {
        if ($operation->status === 'reversed') {
            throw new Exception("??? ??????? ?????? ??????.");
        }

        DB::transaction(function () use ($operation, $userId) {
            if ($operation->financial_operation_id) {
                $this->engine->reverseOperation($operation->financial_operation_id, "??? ????? ????");
            }
            
            $operation->status = 'reversed';
            $operation->updated_by = $userId;
            $operation->save();
        });
    }

    /**
     * ??????? ??? ???? ??????
     */
    public function getPartnerStatement(int $partnerId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = PartnerOperation::where('partner_id', $partnerId)->where('status', 'completed');

        if ($fromDate) {
            $query->whereDate('operation_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('operation_date', '<=', $toDate);
        }

        $operations = (clone $query)
            ->with(['cashBox'])
            ->orderBy('operation_date', 'desc')
            ->get();

        $totalDeposits = (float) (clone $query)->whereIn('type', self::DEPOSIT_TYPES)->sum('amount');
        $totalWithdrawals = (float) (clone $query)->whereIn('type', self::WITHDRAW_TYPES)->sum('amount');
        $netBalance = $totalDeposits - $totalWithdrawals;

        $breakdown = [];
        $allTypes = array_merge(self::DEPOSIT_TYPES, self::WITHDRAW_TYPES);
        foreach ($allTypes as $t) {
            $breakdown[$t] = [
                'type' => $t,
                'label' => $this->getTypeLabelText($t),
                'total_amount' => (float) (clone $query)->where('type', $t)->sum('amount'),
                'count' => (clone $query)->where('type', $t)->count(),
            ];
        }

        return [
            'partner_id' => $partnerId,
            'summary' => [
                'total_deposits' => $totalDeposits,
                'total_withdrawals' => $totalWithdrawals,
                'net_balance' => $netBalance,
            ],
            'breakdown' => $breakdown,
            'operations_count' => $operations->count(),
            'operations' => $operations,
        ];
    }

    public function getTypeLabelText(string $type): string
    {
        return match ($type) {
            'capital_increase' => '????? ??? ???',
            'capital_withdrawal' => '??? ?? ??? ?????',
            'partner_loan_given' => '????? ??? ??????',
            'partner_loan_repaid' => '???? ??? ??????',
            'profit_distribution' => '????? ?????',
            'loss_coverage' => '????? ?????',
            default => $type,
        };
    }
}
