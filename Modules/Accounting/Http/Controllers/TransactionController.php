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
            $companyId = $authUser->active_company_id ?? null;
            if (!$authUser || !$companyId) return api_unauthorized('يتطلب المصادقة.');

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('balance.transfer')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لتحويل الأموال.');
            }

            // التحقق من المدخلات بناءً على الخزائن ككيانات مالية وليس المستخدمين
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'from_cash_box_id' => 'required|exists:cash_boxes,id',
                'to_cash_box_id' => 'required|exists:cash_boxes,id|different:from_cash_box_id',
                'description' => 'nullable|string',
                'operation_id' => 'nullable|uuid', // لدعم Idempotency من الواجهة إن وجد
            ]);

            $fromCashBoxId = $validated['from_cash_box_id'];
            $toCashBoxId = $validated['to_cash_box_id'];

            // لا نستدعي Global Scopes أثناء البحث لنتحقق من تبعية الخزنة للشركة يدوياً
            $fromCashBox = CashBox::withoutGlobalScopes()->findOrFail($fromCashBoxId);
            $toCashBox = CashBox::withoutGlobalScopes()->findOrFail($toCashBoxId);

            // التحقق من الشركة
            if ($fromCashBox->company_id !== $companyId || $toCashBox->company_id !== $companyId) {
                return api_forbidden('لا يمكن إجراء تحويل لخزينة خارج إطار شركتك النشطة.');
            }

            if (!$authUser->canAccessCashBox($fromCashBox)) {
                return api_forbidden('ليس لديك صلاحية الوصول إلى الخزينة المصدر.');
            }

            if (!$authUser->canAccessCashBox($toCashBox) && !$authUser->hasPermissionTo(perm_key('balance.transfer_any')) && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('ليس لديك صلاحية الوصول إلى الخزينة الهدف.');
            }

            $engine = app(\App\Contracts\FinancialEngineInterface::class);
            $operationId = $validated['operation_id'] ?? (string) \Illuminate\Support\Str::uuid();
            $description = $validated['description'] ?? "تحويل داخلي من {$fromCashBox->name} إلى {$toCashBox->name}";

            DB::beginTransaction();
            try {
                $engine->transferCash(
                    $fromCashBoxId,
                    $toCashBoxId,
                    (float)$validated['amount'],
                    $operationId,
                    $description
                );

                DB::commit();
                return api_success([], 'تم التحويل بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                $code = (str_contains($e->getMessage(), 'الرصيد غير كاف') || str_contains($e->getMessage(), 'insufficient')) ? 422 : 500;
                return api_error($e->getMessage() ?: 'فشل التحويل. يرجى المحاولة مرة أخرى.', [], $code);
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
                ->where('company_id', $authUser->active_company_id)
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
            $companyId = $authUser->active_company_id ?? null;
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

            $cashBoxId = $validated['cash_box_id'] ?? $authUser->getDefaultCashBoxForCompany($companyId)?->id;
            if (!$cashBoxId) return api_error('لا توجد خزنة صالحة لإتمام العملية.', [], 422);

            $cashBox = CashBox::findOrFail($cashBoxId);
            if (!$authUser->canAccessCashBox($cashBox)) {
                return api_forbidden('ليس لديك صلاحية الوصول إلى هذه الخزينة.');
            }

            $engine = app(\App\Contracts\FinancialEngineInterface::class);
            $operationId = (string) \Illuminate\Support\Str::uuid();
            $description = $validated['description'] ?? 'إيداع نقدي خارجي';

            DB::beginTransaction();
            try {
                $engine->receiveMoney(
                    (float)$validated['amount'],
                    $cashBoxId,
                    $operationId,
                    [
                        'company_id' => $companyId,
                        'user_id' => $targetUserId,
                        'description' => $description,
                    ]
                );

                DB::commit();
                return api_success([], 'تم الإيداع بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error($e->getMessage() ?: 'فشل الإيداع. يرجى المحاولة مرة أخرى.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function withdraw(Request $request)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id ?? null;
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

            $cashBoxId = $validated['cash_box_id'] ?? $authUser->getDefaultCashBoxForCompany($companyId)?->id;
            if (!$cashBoxId) return api_error('لا توجد خزنة صالحة لإتمام العملية.', [], 422);

            $cashBox = CashBox::findOrFail($cashBoxId);
            if (!$authUser->canAccessCashBox($cashBox)) {
                return api_forbidden('ليس لديك صلاحية الوصول إلى هذه الخزينة.');
            }

            $engine = app(\App\Contracts\FinancialEngineInterface::class);
            $operationId = (string) \Illuminate\Support\Str::uuid();
            $description = $validated['description'] ?? 'سحب نقدي خارجي';

            DB::beginTransaction();
            try {
                $engine->payMoney(
                    (float)$validated['amount'],
                    $cashBoxId,
                    $operationId,
                    [
                        'company_id' => $companyId,
                        'user_id' => $targetUserId,
                        'description' => $description,
                    ]
                );

                DB::commit();
                return api_success([], 'تم السحب بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                $code = (str_contains($e->getMessage(), 'الرصيد غير كاف') || str_contains($e->getMessage(), 'insufficient')) ? 422 : 500;
                return api_error($e->getMessage() ?: 'فشل السحب. يرجى المحاولة مرة أخرى.', [], $code);
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

            // تجاوز فلاتر الفروع الصامتة لمديري الشركات والسوبر أدمن لعرض سجل العمليات المالي بالكامل
            if ($authUser->hasAnyPermission([perm_key('admin.super'), perm_key('admin.company'), perm_key('transactions.view_all')])) {
                $query->withoutGlobalScope('branch_filter');
            }

            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // All
            } elseif ($authUser->hasAnyPermission([perm_key('transactions.view_all'), perm_key('admin.company')])) {
                $query->where('company_id', $authUser->active_company_id);
            } else {
                $query->where('user_id', $authUser->id);
            }

            // تصفية المعاملات حسب حركات التحويل (الدور الأساسي) أو سجلات المعاملات المحدثة
            if ($request->has('is_transfer')) {
                $query->where('is_transfer', $request->boolean('is_transfer'));
            }

            if ($request->filled('type')) $query->where('type', $request->input('type'));

            if ($request->filled('user_id')) {
                $query->withoutGlobalScope('branch_filter');
                $userId = $request->input('user_id');
                $query->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                      ->orWhere('target_user_id', $userId);
                });
            }

            $perPage = max(1, $request->get('per_page', 10));
            $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return api_success(TransactionResource::collection($transactions), 'تم استرداد المعاملات بنجاح.');
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

                // Permission check
                if (!$authUser->hasPermissionTo(perm_key('admin.super')) && $transaction->company_id !== $authUser->active_company_id) {
                    return api_forbidden('ليس لديك إذن.');
                }

                if (is_null($transaction->financial_operation_id)) {
                    return api_error('المعاملة المحددة لا تنتمي لعملية مالية مسجلة بالنظام ليمكن عكسها.', [], 422);
                }

                $engine = app(\App\Contracts\FinancialEngineInterface::class);
                $reversalOpId = $engine->reverseOperation($transaction->financial_operation_id, 'عكس المعاملة يدوياً عبر لوحة التحكم');

                $reversedTransaction = Transaction::where('financial_operation_id', $reversalOpId)
                    ->where('cashbox_id', $transaction->cashbox_id)
                    ->first();

                if (!$reversedTransaction) {
                    $reversedTransaction = Transaction::where('financial_operation_id', $reversalOpId)->first();
                }

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
