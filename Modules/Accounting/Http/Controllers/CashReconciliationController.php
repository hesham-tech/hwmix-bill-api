<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Accounting\Models\CashReconciliation;
use Modules\Accounting\Models\CashBox;
use Modules\Accounting\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Throwable;

/**
 * متحكم تسوية ومطابقة الخزن والحسابات البنكية
 */
class CashReconciliationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $query = CashReconciliation::with(['cashbox', 'approver', 'creator']);

            if ($request->filled('cashbox_id')) {
                $query->where('cashbox_id', $request->input('cashbox_id'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            $perPage = max(1, (int)$request->get('per_page', 10));
            $reconciliations = $query->orderBy('reconciliation_date', 'desc')->paginate($perPage);

            return api_success($reconciliations, 'تم استرداد عمليات التسوية بنجاح.');
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
                'reconciliation_date' => 'required|date|before_or_equal:today',
                'physical_balance' => 'required|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            $cashbox = CashBox::findOrFail($validated['cashbox_id']);
            if ($cashbox->company_id !== $companyId) {
                return api_forbidden('الخزينة المحددة لا تنتمي لشركتك الحالية.');
            }

            $reconciliationDate = Carbon::parse($validated['reconciliation_date']);

            // احتساب الرصيد الدفتري في ذلك التاريخ التاريخي
            $currentBalance = (float)$cashbox->balance;
            $transactionsAfter = Transaction::where('cashbox_id', $cashbox->id)
                ->where('created_at', '>', $reconciliationDate->endOfDay())
                ->get();

            $bookBalance = $currentBalance;
            foreach ($transactionsAfter as $tx) {
                if (in_array($tx->type, ['deposit', 'transfer_in', 'reverse_withdraw'])) {
                    $bookBalance -= (float)$tx->amount;
                } elseif (in_array($tx->type, ['withdraw', 'transfer_out', 'reverse_deposit'])) {
                    $bookBalance += (float)$tx->amount;
                }
            }

            $physicalBalance = (float)$validated['physical_balance'];
            $difference = $physicalBalance - $bookBalance;

            $reconciliation = CashReconciliation::create([
                'company_id' => $companyId,
                'branch_id' => $cashbox->branch_id,
                'cashbox_id' => $cashbox->id,
                'reconciliation_date' => $validated['reconciliation_date'],
                'book_balance' => $bookBalance,
                'physical_balance' => $physicalBalance,
                'difference' => $difference,
                'status' => 'pending',
                'notes' => $validated['notes'],
                'created_by' => $authUser->id,
            ]);

            return api_success($reconciliation, 'تم تسجيل التسوية قيد المراجعة بنجاح.', 201);
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function approve(string $id): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id ?? null;
            if (!$authUser || !$companyId) return api_unauthorized('يتطلب المصادقة.');

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لاعتماد التسويات.');
            }

            $reconciliation = CashReconciliation::findOrFail($id);
            if ($reconciliation->company_id !== $companyId) {
                return api_forbidden('التسوية المحددة لا تنتمي لشركتك الحالية.');
            }

            if ($reconciliation->status !== 'pending') {
                return api_error('لا يمكن اعتماد تسوية غير معلقة.', [], 400);
            }

            DB::transaction(function () use ($reconciliation, $authUser) {
                $reconciliation->update([
                    'status' => 'approved',
                    'approved_by' => $authUser->id,
                    'approved_at' => now(),
                ]);

                app(\App\Contracts\FinancialEngineInterface::class)->processReconciliationApproval($reconciliation);
            });

            return api_success($reconciliation, 'تم اعتماد تسوية ومطابقة الرصيد بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}
