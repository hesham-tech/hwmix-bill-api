<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Accounting\Models\OwnerFundTransaction;
use Modules\Accounting\Models\CashBox;
use Modules\Accounting\Services\FinancialLedgerService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * متحكم معاملات أموال الملاك والشركاء ورأس المال والقروض
 */
class OwnerFundTransactionController extends Controller
{
    protected FinancialLedgerService $ledgerService;

    public function __construct(FinancialLedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $query = OwnerFundTransaction::with(['cashbox', 'user', 'branch']);

            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }
            if ($request->filled('cashbox_id')) {
                $query->where('cashbox_id', $request->input('cashbox_id'));
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }

            $perPage = max(1, (int)$request->get('per_page', 10));
            $transactions = $query->orderBy('entry_date', 'desc')->paginate($perPage);

            return api_success($transactions, 'تم استرداد المعاملات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id ?? null;
            if (!$authUser || !$companyId) return api_unauthorized('يتطلب المصادقة.');

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإجراء هذه العملية.');
            }

            $validated = $request->validate([
                'cashbox_id' => 'required|exists:cash_boxes,id',
                'user_id' => 'required|exists:users,id',
                'type' => 'required|in:capital_increase,partner_contribution,loan_from_owner,loan_to_owner,advance_from_owner,advance_to_partner,drawings,profit_distribution',
                'amount' => 'required|numeric|min:0.01',
                'entry_date' => 'required|date',
                'description' => 'nullable|string',
            ]);

            $cashbox = CashBox::findOrFail($validated['cashbox_id']);
            if ($cashbox->company_id !== $companyId) {
                return api_forbidden('الخزينة المحددة لا تنتمي لشركتك الحالية.');
            }

            $targetUser = User::findOrFail($validated['user_id']);

            DB::beginTransaction();
            try {
                $type = $validated['type'];
                $amount = (float)$validated['amount'];
                $isDeposit = in_array($type, ['capital_increase', 'partner_contribution', 'loan_from_owner', 'advance_from_owner']);

                $engine = app(\App\Contracts\FinancialEngineInterface::class);
                $operationId = (string) \Illuminate\Support\Str::uuid();

                if ($isDeposit) {
                    $engine->receiveMoney($amount, $cashbox->id, $operationId, [
                        'company_id' => $companyId,
                        'user_id' => $targetUser->id,
                        'created_by' => $authUser->id,
                        'description' => $validated['description'] ?? "إيداع أملاك: {$type}",
                    ]);
                } else {
                    $engine->payMoney($amount, $cashbox->id, $operationId, [
                        'company_id' => $companyId,
                        'user_id' => $targetUser->id,
                        'created_by' => $authUser->id,
                        'description' => $validated['description'] ?? "سحب أملاك: {$type}",
                    ]);
                }

                $tx = OwnerFundTransaction::create([
                    'company_id' => $companyId,
                    'branch_id' => $cashbox->branch_id,
                    'cashbox_id' => $cashbox->id,
                    'user_id' => $targetUser->id,
                    'type' => $type,
                    'amount' => $amount,
                    'entry_date' => $validated['entry_date'],
                    'description' => $validated['description'],
                    'created_by' => $authUser->id,
                ]);

                $this->ledgerService->recordOwnerFundTransaction($tx);

                DB::commit();

                return api_success($tx, 'تم تسجيل معاملة أموال الملاك بنجاح.', 201);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e, 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}
