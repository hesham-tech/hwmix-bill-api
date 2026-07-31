<?php
// متحكم لإدارة خطوط الاتصال والمحافظ الإلكترونية بكاش هونكس HwnixCash.

namespace Modules\HwnixCash\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HwnixCash\Http\Requests\Web\UpdateLineRequest;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Transformers\LineResource;

class LineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->active_company_id ?? $user->company_id;

        $hasAccess = true;
        try {
            $hasAccess = $user->can(perm_key('admin.super'))
                || $user->can(perm_key('admin.company'))
                || $user->can(perm_key('hwnix_cash.view_all'))
                || $user->can(perm_key('hwnix_cash.view_self'))
                || $user->hasCapability('is_internal', $companyId);
        } catch (\Throwable $e) {
            $hasAccess = true;
        }

        if (!$hasAccess) {
            return api_forbidden('غير مصرح لك بعرض الخطوط.');
        }

        $lines = HwnixCashLine::with(['device', 'financialAccounts.messageSource'])
            ->where('company_id', $companyId)
            ->get();

        return api_success(LineResource::collection($lines), 'تم جلب قائمة الخطوط بنجاح.');
    }

    public function update(UpdateLineRequest $request, $id): JsonResponse
    {
        if (!is_numeric($id)) {
            return api_error('المعرف غير صالح.', [], 400);
        }

        $user = $request->user();
        $companyId = $user->active_company_id ?? $user->company_id;

        $line = HwnixCashLine::where('id', $id)
            ->where('company_id', $companyId)
            ->first();

        if (!$line) {
            return api_error('الخط غير متوفر أو لا ينتمي لشركتك.', [], 400);
        }

        $line->update($request->validated());

        return api_success(new LineResource($line->load('device')), 'تم تحديث بيانات الخط بنجاح.');
    }

    public function reconcile(Request $request, $id): JsonResponse
    {
        if (!is_numeric($id)) {
            return api_error('المعرف غير صالح.', [], 400);
        }
        $user = $request->user();
        $companyId = $user->active_company_id ?? $user->company_id;

        $request->validate([
            'target_balance' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:500',
        ]);

        $line = HwnixCashLine::where('id', $id)
            ->where('company_id', $companyId)
            ->first();

        if (!$line) {
            return api_error('الخط المالي غير متوفر.', [], 404);
        }

        $targetBalance = (float) $request->target_balance;
        $oldBalance = (float) $line->balance;
        $difference = round($targetBalance - $oldBalance, 2);

        \Illuminate\Support\Facades\DB::transaction(function () use ($line, $user, $companyId, $targetBalance, $oldBalance, $difference, $request) {
            // 1. تحديث الرصيد الحسابي للخط
            $line->update([
                'balance' => $targetBalance,
            ]);

            // 2. تسجيل قيد/معاملة تسوية مالية رسمية بجدول المعاملات للتدقيق المحاسبي (FAC-001)
            \Modules\HwnixCash\Models\HwnixCashWalletTransaction::create([
                'company_id' => $companyId,
                'created_by' => $user->id,
                'line_id' => $line->id,
                'operation_type' => \Modules\HwnixCash\Domain\Enums\WalletOperationType::RECONCILIATION->value,
                'provider' => $line->carrier ?? 'vodafone_cash',
                'status' => \Modules\HwnixCash\Domain\Enums\WalletTransactionStatus::SUCCESS->value,
                'source' => \Modules\HwnixCash\Domain\Enums\WalletTransactionSource::MANUAL->value,
                'amount' => abs($difference),
                'fee' => 0.00,
                'balance_after' => $targetBalance,
                'currency' => 'EGP',
                'operation_number' => 'REC-' . date('YmdHis') . '-' . $line->id,
                'operation_at' => now(),
                'raw_sms' => 'تسوية مالية يدوية للرصيد الحسابي بالرصيد الفعلي',
                'metadata' => [
                    'type' => 'balance_reconciliation',
                    'old_balance' => $oldBalance,
                    'new_balance' => $targetBalance,
                    'difference' => $difference,
                    'actual_balance' => (float) $line->actual_balance,
                    'note' => $request->note ?? 'تسوية الرصيد الحسابي بالرصيد الفعلي',
                ],
            ]);
        });

        return api_success(new LineResource($line->fresh('device')), 'تمت تسوية الرصيد الحسابي وتسجيل حركة التسوية المالية بنجاح.');
    }
}
