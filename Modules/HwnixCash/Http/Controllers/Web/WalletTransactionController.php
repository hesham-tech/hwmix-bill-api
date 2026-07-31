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
