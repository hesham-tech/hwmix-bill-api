<?php
// متحكم لإدارة معاملات المحافظ الإلكترونية وعرض السجلات المالية للوحة تحكم الويب.

namespace Modules\HwnixCash\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HwnixCash\Domain\Contracts\HwnixCashWalletTransactionRepositoryInterface;
use Modules\HwnixCash\DTOs\WalletTransactionData;
use Modules\HwnixCash\Http\Requests\Web\StoreWalletTransactionRequest;
use Modules\HwnixCash\Http\Requests\Web\UpdateWalletTransactionRequest;
use Modules\HwnixCash\Models\HwnixCashWalletTransaction;
use Modules\HwnixCash\Transformers\WalletTransactionResource;

class WalletTransactionController extends Controller
{
    public function __construct(
        protected HwnixCashWalletTransactionRepositoryInterface $repository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->active_company_id ?? $user->company_id;

        $hasAccess = true;
        try {
            $hasAccess = $user->can(perm_key('admin.super'))
                || $user->can(perm_key('admin.company'))
                || $user->can(perm_key('hwnix_cash_wallet_transactions.view_all'))
                || $user->can(perm_key('hwnix_cash_wallet_transactions.view_self'))
                || $user->hasCapability('is_internal', $companyId);
        } catch (\Throwable $e) {
            $hasAccess = true;
        }

        if (!$hasAccess) {
            return api_forbidden('غير مصرح لك بعرض معاملات المحافظ الإلكترونية.');
        }

        $query = HwnixCashWalletTransaction::with(['financialAccount.line', 'financialAccount.messageSource'])
            ->where('company_id', $companyId);

        if ($request->filled('financial_account_id')) {
            $query->where('financial_account_id', $request->financial_account_id);
        }

        if ($request->filled('line_id')) {
            $query->whereHas('financialAccount', function ($q) use ($request) {
                $q->where('line_id', $request->line_id);
            });
        }

        if ($request->filled('operation_type')) {
            $query->where('operation_type', $request->operation_type);
        }

        if ($request->filled('provider')) {
            $query->where('provider', $request->provider);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // 1. البحث الشامل (Search Query)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('operation_number', 'like', "%{$search}%")
                    ->orWhere('target_phone', 'like', "%{$search}%")
                    ->orWhere('target_name', 'like', "%{$search}%")
                    ->orWhere('raw_sms', 'like', "%{$search}%")
                    ->orWhere('bill_number', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('financialAccount', function ($faQuery) use ($search) {
                        $faQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('sender_identifier', 'like', "%{$search}%")
                            ->orWhereHas('line', function ($lineQuery) use ($search) {
                                $lineQuery->where('phone_number', 'like', "%{$search}%")
                                    ->orWhere('device_name', 'like', "%{$search}%");
                            });
                    });

                if (is_numeric($search)) {
                    $q->orWhere('amount', (float) $search);
                }
            });
        }

        // 2. تصفية التواريخ (Date Range Filter)
        if ($request->filled('date_from')) {
            $query->whereDate('operation_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('operation_at', '<=', $request->date_to);
        }

        // 3. تصفية منفذ التحليل (Parser Executor Filter)
        if ($request->filled('parsed_by')) {
            $parsedBy = $request->parsed_by;
            if ($parsedBy === 'rule_based') {
                $query->where(function ($q) {
                    $q->where('metadata->parser_stage', 'rule_based')
                        ->orWhere('metadata->parsed_by', 'like', '%Pattern%')
                        ->orWhere('metadata->parsed_by', 'like', '%Vf%')
                        ->orWhere('metadata->parsed_by', 'like', '%rule_based%');
                });
            } elseif ($parsedBy === 'ai') {
                $query->where(function ($q) {
                    $q->where('metadata->parser_stage', 'ai')
                        ->orWhere('metadata->parsed_by', 'like', '%AI%')
                        ->orWhereNotNull('metadata->normalized_dto->executionMetadata->ai_model');
                });
            } elseif ($parsedBy === 'system') {
                $query->where(function ($q) {
                    $q->where('operation_type', 'reconciliation')
                        ->orWhere('source', 'system')
                        ->orWhere('metadata->parsed_by', 'SystemReconciliation')
                        ->orWhereNotNull('metadata->reconciled_by');
                });
            } elseif ($parsedBy === 'manual') {
                $query->where('source', 'manual');
            }
        }

        $transactions = $query->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 20);

        return api_success(WalletTransactionResource::collection($transactions), 'تم جلب قائمة معاملات المحافظ بنجاح.');
    }

    public function store(StoreWalletTransactionRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('admin.company')) && !$user->hasPermissionTo(perm_key('hwnix_cash_wallet_transactions.create'))) {
            return api_forbidden('غير مصرح لك بإضافة معاملات محفظة.');
        }

        $dto = WalletTransactionData::fromArray($request->validated());
        $entity = $this->repository->create($dto, $user->company_id, $user->id);

        return api_success(new WalletTransactionResource(HwnixCashWalletTransaction::find($entity->id)), 'تم تسجيل معاملة المحفظة بنجاح.', 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        if (!is_numeric($id)) {
            return api_error('المعاملة غير متوفرة أو المعرف غير صالح.', [], 404);
        }

        $user = $request->user();
        $transaction = HwnixCashWalletTransaction::where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$transaction) {
            return api_error('المعاملة غير متوفرة أو لا تنتمي لشركتك.', [], 404);
        }

        return api_success(new WalletTransactionResource($transaction), 'تم جلب تفاصيل المعاملة بنجاح.');
    }

    public function update(UpdateWalletTransactionRequest $request, $id): JsonResponse
    {
        if (!is_numeric($id)) {
            return api_error('المعاملة غير متوفرة أو المعرف غير صالح.', [], 404);
        }

        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('admin.company')) && !$user->hasPermissionTo(perm_key('hwnix_cash_wallet_transactions.edit_all')) && !$user->hasPermissionTo(perm_key('hwnix_cash_wallet_transactions.edit_self'))) {
            return api_forbidden('غير مصرح لك بتعديل معاملة المحفظة.');
        }

        $transaction = HwnixCashWalletTransaction::where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$transaction) {
            return api_error('المعاملة غير متوفرة.', [], 404);
        }

        $dto = WalletTransactionData::fromArray($request->validated());
        $updatedEntity = $this->repository->update((int) $id, $dto);

        return api_success(new WalletTransactionResource(HwnixCashWalletTransaction::find($updatedEntity->id)), 'تم تحديث بيانات المعاملة بنجاح.');
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        if (!is_numeric($id)) {
            return api_error('المعاملة غير متوفرة أو المعرف غير صالح.', [], 404);
        }

        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('admin.company')) && !$user->hasPermissionTo(perm_key('hwnix_cash_wallet_transactions.delete_all'))) {
            return api_forbidden('غير مصرح لك بحذف المعاملة.');
        }

        $transaction = HwnixCashWalletTransaction::where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$transaction) {
            return api_error('المعاملة غير متوفرة.', [], 404);
        }

        $this->repository->delete((int) $id);

        return api_success(null, 'تم حذف المعاملة بنجاح.');
    }
}
