<?php
// متحكم إدارة الحسابات المالية بكاش هونكس HwnixCash.

namespace Modules\HwnixCash\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\HwnixCash\Domain\Enums\WalletOperationType;
use Modules\HwnixCash\Domain\Enums\WalletProvider;
use Modules\HwnixCash\Http\Requests\StoreFinancialAccountRequest;
use Modules\HwnixCash\Http\Requests\UpdateFinancialAccountRequest;
use Modules\HwnixCash\Models\HwnixCashFinancialAccount;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Models\HwnixCashMessage;
use Modules\HwnixCashModels\HwnixCashMessageSource;
use Modules\HwnixCash\Models\HwnixCashWalletTransaction;
use Modules\HwnixCash\Transformers\FinancialAccountResource;

class FinancialAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->active_company_id ?? $user->company_id;

        $query = HwnixCashFinancialAccount::with(['line', 'messageSource'])
            ->where('company_id', $companyId);

        if ($request->has('line_id') && !empty($request->line_id)) {
            $query->where('line_id', $request->line_id);
        }

        $accounts = $query->latest()->get();

        return api_success(
            FinancialAccountResource::collection($accounts),
            'تم جلب قائمة الحسابات المالية بنجاح.'
        );
    }

    public function distinctSenders(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->active_company_id ?? $user->company_id;

        $senders = HwnixCashMessage::where('company_id', $companyId)
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->distinct()
            ->pluck('phone_number');

        return api_success($senders, 'تم جلب أسماء المرسلين المكتشفة بنجاح.');
    }

    public function store(StoreFinancialAccountRequest $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->active_company_id ?? $user->company_id;
        $validated = $request->validated();

        $account = DB::transaction(function () use ($validated, $companyId, $user) {
            // 1. التأكد من وجود MessageSource أو إنشاؤه
            $provider = WalletProvider::VODAFONE_CASH;
            $search = strtolower($validated['sender_identifier']);
            if (str_contains($search, 'instapay')) {
                $provider = WalletProvider::INSTAPAY;
            } elseif (str_contains($search, 'orange')) {
                $provider = WalletProvider::ORANGE_CASH;
            } elseif (str_contains($search, 'etisalat') || str_contains($search, 'e&')) {
                $provider = WalletProvider::ETISALAT_CASH;
            } elseif (str_contains($search, 'we')) {
                $provider = WalletProvider::WE_CASH;
            }

            $messageSource = \Modules\HwnixCash\Models\HwnixCashMessageSource::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'sender_identifier' => trim($validated['sender_identifier']),
                ],
                [
                    'created_by' => $user->id,
                    'provider' => $provider->value,
                    'is_active' => true,
                    'note' => 'تم إنشاؤه تلقائياً عند إضافة الحساب المالي',
                ]
            );

            // 2. إنشاء الحساب المالي المرتبط بالخط ومصدر الرسائل
            return HwnixCashFinancialAccount::create([
                'company_id' => $companyId,
                'created_by' => $user->id,
                'line_id' => $validated['line_id'],
                'message_source_id' => $messageSource->id,
                'name' => trim($validated['name']),
                'account_number' => isset($validated['account_number']) ? trim($validated['account_number']) : null,
                'daily_withdraw_limit' => $validated['daily_withdraw_limit'] ?? null,
                'daily_deposit_limit' => $validated['daily_deposit_limit'] ?? null,
                'monthly_withdraw_limit' => $validated['monthly_withdraw_limit'] ?? null,
                'monthly_deposit_limit' => $validated['monthly_deposit_limit'] ?? null,
                'status' => 'active',
                'note' => $validated['note'] ?? null,
            ]);
        });

        $account->load(['line', 'messageSource']);

        return api_success(
            new FinancialAccountResource($account),
            'تم إنشاء الحساب المالي بنجاح.'
        );
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->active_company_id ?? $user->company_id;

        $account = HwnixCashFinancialAccount::with(['line', 'messageSource'])
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return api_success(
            new FinancialAccountResource($account),
            'تم جلب بيانات الحساب المالي بنجاح.'
        );
    }

    public function update(int $id, UpdateFinancialAccountRequest $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->active_company_id ?? $user->company_id;

        $account = HwnixCashFinancialAccount::where('company_id', $companyId)
            ->findOrFail($id);

        $account->update($request->validated());
        $account->load(['line', 'messageSource']);

        return api_success(
            new FinancialAccountResource($account),
            'تم تحديث الحساب المالي بنجاح.'
        );
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->active_company_id ?? $user->company_id;

        $account = HwnixCashFinancialAccount::where('company_id', $companyId)
            ->findOrFail($id);

        $account->delete();

        return api_success(null, 'تم حذف الحساب المالي بنجاح.');
    }

    public function reconcile(int $id, \Modules\HwnixCash\Http\Requests\ReconcileFinancialAccountRequest $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->active_company_id ?? $user->company_id;

        $account = HwnixCashFinancialAccount::where('company_id', $companyId)
            ->findOrFail($id);

        $reason = trim($request->validated('reason'));
        $oldBalance = (float) $account->balance;
        $newBalance = (float) $account->actual_balance;
        $difference = round($newBalance - $oldBalance, 2);

        if (abs($difference) < 0.01) {
            return api_error('الرصيد الحسابي مطابق بالفعل للرصيد الفعلي، لا توجد تسوية مضافة.', 422);
        }

        DB::transaction(function () use ($account, $oldBalance, $newBalance, $difference, $reason, $companyId, $user) {
            $account->update([
                'balance' => $newBalance,
            ]);

            HwnixCashWalletTransaction::create([
                'company_id' => $companyId,
                'created_by' => $user->id,
                'financial_account_id' => $account->id,
                'line_id' => $account->line_id,
                'operation_type' => WalletOperationType::RECONCILIATION->value,
                'provider' => $account->messageSource?->provider?->value ?? WalletProvider::OTHER->value,
                'status' => 'success',
                'source' => 'system',
                'amount' => abs($difference),
                'fee' => 0.00,
                'balance_after' => $newBalance,
                'currency' => 'EGP',
                'operation_number' => 'REC-' . time() . '-' . rand(100, 999),
                'operation_at' => now(),
                'target_phone' => null,
                'target_name' => 'تسوية رصيد حساب مالي',
                'raw_sms' => "تسوية يدوية للرصيد الحسابي من {$oldBalance} إلى {$newBalance} EGP - السبب: {$reason}",
                'metadata' => [
                    'old_balance' => $oldBalance,
                    'new_balance' => $newBalance,
                    'difference' => $difference,
                    'reason' => $reason,
                    'reconciled_by' => $user->id,
                ],
            ]);
        });

        $account->load(['line', 'messageSource']);

        return api_success(
            new FinancialAccountResource($account),
            'تمت تسوية الرصيد الحسابي بالرصيد الفعلي بنجاح وتسجيل قيد التسوية.'
        );
    }
}
