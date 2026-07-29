<?php

namespace App\Services;

use App\Models\PartnerOperation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * خدمة إدارة وإجراء العمليات المالية والتقارير الخاصة بالشركاء
 */
class PartnerOperationService
{
    protected CashService $cashService;
    protected FinancialLedgerService $ledgerService;

    public function __construct(CashService $cashService, FinancialLedgerService $ledgerService)
    {
        $this->cashService = $cashService;
        $this->ledgerService = $ledgerService;
    }

    /**
     * أنواع العمليات التي تعتبر مدخلات ومساهمات نقدية (إيداع)
     */
    public const DEPOSIT_TYPES = [
        'capital_increase',
        'partner_loan_given',
        'loss_coverage',
    ];

    /**
     * أنواع العمليات التي تعتبر مخرجات ومسحوبات نقدية (سحب)
     */
    public const WITHDRAW_TYPES = [
        'capital_withdrawal',
        'partner_loan_repaid',
        'profit_distribution',
    ];

    /**
     * تنفيذ عملية مالية جديدة للشريك مع توثيق الأثر المالي في السيولة ودفتر الأستاذ
     */
    public function executeOperation(array $data): PartnerOperation
    {
        return DB::transaction(function () use ($data) {
            $companyId = Auth::user()->active_company_id ?? null;
            if (!$companyId) {
                throw new Exception('لم يتم تحديد الشركة النشطة للمستخدم.');
            }

            $type = $data['type'];
            $amount = (float) $data['amount'];
            $cashBoxId = (int) $data['cashbox_id'];
            $partnerId = (int) $data['partner_id'];
            $operationDate = isset($data['operation_date']) ? $data['operation_date'] : now();

            // 1. إنشاء السجل التجاري للعملية
            $partnerOperation = PartnerOperation::create([
                'company_id' => $companyId,
                'partner_id' => $partnerId,
                'cashbox_id' => $cashBoxId,
                'type' => $type,
                'amount' => $amount,
                'operation_date' => $operationDate,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $operationId = "partner_op_" . $partnerOperation->id;
            $partner = User::find($partnerId);
            $partnerName = $partner ? ($partner->nickname ?? $partner->name) : "#{$partnerId}";
            $typeLabel = $this->getTypeLabelText($type);
            $desc = "عملية شركاء ({$typeLabel}) - الشريك: {$partnerName}";

            // 2. تحديث الحركة النقدية في الخزنة المسجلة عبر CashService
            if (in_array($type, self::DEPOSIT_TYPES)) {
                $this->cashService->deposit($amount, $cashBoxId, $operationId, [
                    'description' => $desc,
                    'user_id' => $partnerId,
                    'created_by' => Auth::id(),
                ]);
            } elseif (in_array($type, self::WITHDRAW_TYPES)) {
                $this->cashService->withdraw($amount, $cashBoxId, $operationId, [
                    'description' => $desc,
                    'user_id' => $partnerId,
                    'created_by' => Auth::id(),
                ]);
            } else {
                throw new Exception("نوع عملية الشريك غير مدعوم: {$type}");
            }

            // 3. ربط معرّف الحركة النقدية السريعة بالسجل الرئيسي
            $transaction = Transaction::withoutGlobalScopes()
                ->where('financial_operation_id', $operationId)
                ->first();

            if ($transaction) {
                $partnerOperation->transaction_id = $transaction->id;
                $partnerOperation->save();
            }

            // 4. تسجبل القيد المزدوج في دفتر الأستاذ العام
            $this->ledgerService->recordPartnerOperation($partnerOperation);

            return $partnerOperation->load(['partner', 'cashBox', 'transaction']);
        });
    }

    /**
     * استخراج كشف حساب الشريك والصافي المالي
     */
    public function getPartnerStatement(int $partnerId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = PartnerOperation::where('partner_id', $partnerId);

        if ($fromDate) {
            $query->whereDate('operation_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('operation_date', '<=', $toDate);
        }

        $operations = (clone $query)
            ->with(['cashBox', 'transaction'])
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

    /**
     * إرجاع النص العربي الوصفي لنوع العملية
     */
    public function getTypeLabelText(string $type): string
    {
        return match ($type) {
            'capital_increase' => 'زيادة رأس مال',
            'capital_withdrawal' => 'سحب من رأس المال',
            'partner_loan_given' => 'تقديم قرض للشركة',
            'partner_loan_repaid' => 'سداد قرض الشريك',
            'profit_distribution' => 'توزيع أرباح',
            'loss_coverage' => 'تغطية خسائر',
            default => $type,
        };
    }
}
