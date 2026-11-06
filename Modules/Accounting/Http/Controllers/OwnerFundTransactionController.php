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
                'idempotency_key' => 'nullable|uuid',
            ]);

            $cashbox = CashBox::findOrFail($validated['cashbox_id']);
            if ((int)$cashbox->company_id !== (int)$companyId) {
                return api_forbidden('الخزينة المحددة لا تنتمي لشركتك الحالية.');
            }

            $targetUser = User::findOrFail($validated['user_id']);
            // User::company_id is a compatibility accessor for the REQUEST company;
            // it is not evidence that another identity belongs to that company.
            if ((int)$targetUser->getRawOriginal('active_company_id') !== (int)$companyId
                && !$targetUser->companies()->whereKey($companyId)->exists()
                && !\Modules\Companies\Models\BusinessRelation::where('company_id',$companyId)->where('user_id',$targetUser->id)->where('is_active',true)->exists()) {
                return api_forbidden('الطرف المحدد لا ينتمي لشركتك الحالية.');
            }

            DB::beginTransaction();
            try {
                $type = $validated['type'];
                $amount = (float)$validated['amount'];
                $isDeposit = in_array($type, ['capital_increase', 'partner_contribution', 'loan_from_owner', 'advance_from_owner']);

                $engine = app(\App\Contracts\FinancialEngineInterface::class);
                $operationId = $validated['idempotency_key'] ?? (string) \Illuminate\Support\Str::uuid();

                // Serialize balance creation and posting for this company/party.
                User::whereKey($targetUser->id)->lockForUpdate()->firstOrFail();
                if(\App\Models\FinancialOperation::whereKey($operationId)->exists()) {
                    DB::rollBack();
                    return api_error('تمت معالجة هذه المحاولة من قبل. استخدم مرجعًا جديدًا لعملية مختلفة.',[],409);
                }
                $tx = OwnerFundTransaction::create([
                    'company_id'=>$companyId,'branch_id'=>$cashbox->branch_id,'cashbox_id'=>$cashbox->id,
                    'user_id'=>$targetUser->id,'type'=>$type,'amount'=>$amount,
                    'entry_date'=>$validated['entry_date'],'description'=>$validated['description']??null,'created_by'=>$authUser->id,
                ]);
                app(\App\Services\FinancialOperationService::class)->createOperation([
                    'id'=>$operationId,'company_id'=>$companyId,'type'=>'partner_fund','status'=>'completed',
                    'amount'=>$amount,'source_type'=>$tx->getMorphClass(),'source_id'=>$tx->id,
                ]);

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

                $this->ledgerService->recordOwnerFundTransaction($tx);
                \App\Models\FinancialLedger::withoutGlobalScopes()->where('source_type',$tx->getMorphClass())
                    ->where('source_id',$tx->id)->whereNull('financial_operation_id')->update(['financial_operation_id'=>$operationId]);
                $relationType=match($type) {
                    'capital_increase','partner_contribution'=>'capital',
                    'drawings','profit_distribution'=>'partner_drawing',
                    'loan_from_owner','loan_to_owner'=>'partner_loan',
                    'advance_from_owner','advance_to_partner'=>'partner_advance',
                };
                $balance=\Modules\Companies\Models\StakeholderFinancialBalance::lockForUpdate()->firstOrCreate(
                    ['company_id'=>$companyId,'user_id'=>$targetUser->id,'relation_type'=>$relationType],
                    ['balance'=>'0.00','created_by'=>$authUser->id]);
                $delta=in_array($type,['loan_to_owner','advance_to_partner'],true)?'-'.$tx->amount:$tx->amount;
                $balance->balance=bcadd($balance->balance,$delta,2);$balance->save();

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

    public function reverse(Request $request, $ownerFundTransaction): JsonResponse
    {
        $user=$request->user();
        if(!$user || (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('admin.company')) && !$user->hasPermissionTo(perm_key('owner_fund_transactions.reverse'))))
            return api_forbidden('ليس لديك صلاحية لعكس هذه المعاملة.');
        $ownerFundTransaction=OwnerFundTransaction::where('company_id',$user->active_company_id)->findOrFail($ownerFundTransaction);
        try {
            DB::transaction(function() use($ownerFundTransaction) {
                $tx=OwnerFundTransaction::whereKey($ownerFundTransaction->id)->lockForUpdate()->firstOrFail();
                $op=\App\Models\FinancialOperation::where('company_id',$tx->company_id)->where('source_type',$tx->getMorphClass())->where('source_id',$tx->id)->where('type','partner_fund')->lockForUpdate()->first();
                if(!$op)throw new \RuntimeException('المعاملة القديمة بلا عملية مالية موثوقة؛ تحتاج مراجعة محاسبية قبل العكس.');
                app(\App\Contracts\FinancialEngineInterface::class)->reverseOperation($op->id,'عكس معاملة أموال الملاك');
            });
            return api_success(null,'تم عكس معاملة أموال الملاك.');
        } catch(\Exception $e) { return api_error($e->getMessage(),[],422); }
    }
}
