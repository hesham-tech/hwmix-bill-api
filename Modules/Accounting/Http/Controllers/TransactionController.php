<?php

namespace Modules\Accounting\Http\Controllers;

use App\Models\User;
use Modules\Accounting\Models\CashBox;
use Modules\Accounting\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Modules\Accounting\Http\Resources\TransactionResource;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * متحكم المعاملات المالية (TransactionController) - موديول المحاسبة
 */
class TransactionController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'customer',
            'targetCustomer',
            'cashbox',
            'targetCashbox',
            'company',
            'creator',
        ];
    }

    public function transfer(Request $request)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;
            if (!$authUser || !$companyId) return api_unauthorized('يتطلب المصادقة.');

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('balance.transfer')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لتحويل الأموال.');
            }

            $validated = $request->validate([
                'from_user_id' => 'nullable|exists:users,id',
                'target_user_id' => 'required|exists:users,id',
                'amount' => 'required|numeric|min:0.01',
                'from_cash_box_id' => 'nullable|exists:cash_boxes,id',
                'to_cash_box_id' => 'nullable|exists:cash_boxes,id|different:from_cash_box_id',
                'description' => 'nullable|string',
            ]);

            $sourceUserId = $validated['from_user_id'] ?? $authUser->id;
            $sourceUser = User::findOrFail($sourceUserId);
            $targetUser = User::findOrFail($validated['target_user_id']);

            if ($sourceUserId != $authUser->id && !$authUser->hasPermissionTo(perm_key('balance.transfer_any')) && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('ليس لديك إذن للتحويل من حساب مستخدم آخر.');
            }

            $fromCashBoxId = $validated['from_cash_box_id'] ?? $sourceUser->getDefaultCashBoxForCompany($companyId)?->id;
            $toCashBoxId = $validated['to_cash_box_id'] ?? $targetUser->getDefaultCashBoxForCompany($companyId)?->id;

            if (!$fromCashBoxId || !$toCashBoxId) return api_error('لا توجد صناديق افتراضية.', [], 422);

            DB::beginTransaction();
            try {
                $sourceUser->transferTo($targetUser, $validated['amount'], $fromCashBoxId, $toCashBoxId, $validated['description'] ?? "تحويل من {$sourceUser->name} إلى {$targetUser->name}");
                DB::commit();
                return api_success([], 'تم التحويل بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e, 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function userTransactions(Request $request, $cashBoxId)
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $query = Transaction::with($this->relations)
                ->where('user_id', $authUser->id)
                ->where('company_id', $authUser->company_id)
                ->where('cashbox_id', $cashBoxId);

            if ($request->filled('type')) $query->where('type', $request->input('type'));

            $perPage = max(1, $request->get('per_page', 20));
            $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return api_success(TransactionResource::collection($transactions), 'تم جلب المعاملات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function deposit(Request $request)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;
            if (!$authUser || !$companyId) return api_unauthorized('يتطلب المصادقة.');

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('balance.deposit')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإجراء إيداع.');
            }

            $validated = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'amount' => 'required|numeric|min:0.01',
                'cash_box_id' => 'nullable|exists:cash_boxes,id',
                'description' => 'nullable|string',
            ]);

            $targetUserId = $validated['user_id'] ?? $authUser->id;
            $targetUser = User::findOrFail($targetUserId);

            if ($targetUserId != $authUser->id && !$authUser->hasPermissionTo(perm_key('balance.deposit_any')) && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('ليس لديك إذن للإيداع في حساب مستخدم آخر.');
            }

            $cashBoxId = $validated['cash_box_id'] ?? $targetUser->getDefaultCashBoxForCompany($companyId)?->id;
            if (!$cashBoxId) return api_error('لا توجد خزنة للمستهدف.', [], 422);

            DB::beginTransaction();
            try {
                $targetUser->deposit($validated['amount'], $cashBoxId, $validated['description'] ?? 'إيداع نقدي خارجي');
                DB::commit();
                return api_success([], 'تم الإيداع بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e, 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function withdraw(Request $request)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;
            if (!$authUser || !$companyId) return api_unauthorized('يتطلب المصادقة.');

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('balance.withdraw')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإجراء سحب.');
            }

            $validated = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'amount' => 'required|numeric|min:0.01',
                'cash_box_id' => 'nullable|exists:cash_boxes,id',
                'description' => 'nullable|string',
            ]);

            $targetUserId = $validated['user_id'] ?? $authUser->id;
            $targetUser = User::findOrFail($targetUserId);

            if ($targetUserId != $authUser->id && !$authUser->hasPermissionTo(perm_key('balance.withdraw_any')) && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('ليس لديك إذن للسحب من حساب مستخدم آخر.');
            }

            $cashBoxId = $validated['cash_box_id'] ?? $targetUser->getDefaultCashBoxForCompany($companyId)?->id;
            if (!$cashBoxId) return api_error('لا توجد خزنة للمستهدف.', [], 422);

            DB::beginTransaction();
            try {
                $targetUser->withdraw($validated['amount'], $cashBoxId, $validated['description'] ?? 'سحب نقدي خارجي');
                DB::commit();
                return api_success([], 'تم السحب بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e, 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function transactions(Request $request)
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $query = Transaction::with($this->relations);

            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // All
            } elseif ($authUser->hasAnyPermission([perm_key('transactions.view_all'), perm_key('admin.company')])) {
                $query->where('company_id', $authUser->company_id);
            } else {
                $query->where('user_id', $authUser->id);
            }

            if ($request->filled('type')) $query->where('type', $request->input('type'));

            $perPage = max(1, $request->get('per_page', 10));
            $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return api_success($transactions, 'تم استرداد المعاملات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function reverseTransaction(string $transactionId)
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            DB::beginTransaction();
            try {
                $transaction = Transaction::findOrFail($transactionId);

                // Permission check simplified for example
                if (!$authUser->hasPermissionTo(perm_key('admin.super')) && $transaction->company_id !== $authUser->company_id) {
                    return api_forbidden('ليس لديك إذن.');
                }

                switch ($transaction->type) {
                    case 'transfer_out':
                    case 'تحويل_صادر':
                        $transaction->reverseTransfer();
                        break;
                    case 'withdraw':
                    case 'سحب':
                        $transaction->reverseWithdraw();
                        break;
                    case 'deposit':
                    case 'إيداع':
                        $transaction->reverseDeposit();
                        break;
                    default:
                        throw new \Exception('نوع المعاملة غير مدعوم للعكس.');
                }

                $reversedTransaction = Transaction::create([
                    'created_by' => $authUser->id,
                    'company_id' => $authUser->company_id,
                    'user_id' => $transaction->user_id,
                    'cashbox_id' => $transaction->cashbox_id,
                    'amount' => -$transaction->amount,
                    'balance_before' => $transaction->balance_after,
                    'balance_after' => $transaction->balance_before,
                    'description' => 'عكس المعاملة الأصلية رقم: ' . $transaction->id,
                    'original_transaction_id' => $transaction->id,
                    'type' => 'reverse_' . $transaction->type,
                ]);

                DB::commit();
                return api_success(new TransactionResource($reversedTransaction), 'تم عكس المعاملة بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e, 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}
