<?php

namespace Modules\AiPlatform\Router;

use Illuminate\Support\Collection;
use Modules\AiPlatform\Contracts\Router\AiRouterInterface;
use Modules\AiPlatform\DTOs\RouterSelectionDTO;
use Modules\AiPlatform\Models\AiProviderAccount;
use Modules\AiPlatform\Models\AiRouterLog;
use Modules\AiPlatform\Enums\Capability;
use Modules\AiPlatform\Enums\ExecutionPolicy;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * محرك التوجيه الذكي لااختيار الحساب والنموذج المناسب للطلبات وإدارة التوزيع والـ Failover المتتابع
 */
class AiRouter implements AiRouterInterface
{
    public function select(Capability $capability, int $companyId, array $requirements, string $strategy): RouterSelectionDTO
    {
        $accounts = $this->selectAll($capability, $companyId, $requirements, $strategy);

        if ($accounts->isEmpty()) {
            Log::error("[AI Milestone Trace] [Router Failure] Selection failed: No active accounts.");
            throw new Exception("لا يوجد حساب مفاتيح ذكاء اصطناعي (API Key) مفعل متاح للنظام بـ Execution Policy المطابقة.");
        }

        $selectedAccount = $accounts->first();

        $activeModel = \Modules\AiPlatform\Models\AiModel::where('ai_provider_id', $selectedAccount->ai_provider_id)
            ->where('is_active', true)
            ->first();

        $selectedModelSlug = $activeModel?->model_id ?? 'gemini-1.5-flash';

        // تسجيل القرار في جدول ai_router_logs
        $log = AiRouterLog::create([
            'company_id' => $companyId,
            'ai_provider_account_id' => $selectedAccount->id,
            'capability' => $capability->value ?? (string) $capability,
            'capability_key' => is_object($capability) ? $capability->value : $capability,
            'strategy' => $strategy,
            'requirements' => json_encode($requirements),
            'status' => 'selected',
        ]);

        return new RouterSelectionDTO(
            accountId: $selectedAccount->id,
            modelId: $selectedModelSlug,
            reason: $strategy,
            consideredAccounts: $accounts->pluck('id')->toArray(),
            decisionMs: 0
        );
    }

    public function selectAll(Capability $capability, int $companyId, array $requirements, string $strategy): Collection
    {
        $executionPolicy = $requirements['execution_policy'] ?? 'shared';

        $query = AiProviderAccount::with('provider')
            ->where('is_active', true)
            ->where('health_status', '!=', 'failed');

        switch ($executionPolicy) {
            case 'system_only':
            case ExecutionPolicy::SYSTEM_ONLY->value:
                // التوجيه الحصري لحسابات النظام دون المساس بحسابات أو أرصدة الشركاء/الشركات
                $query->where(function ($q) {
                    $q->where('account_scope', 'SYSTEM')
                      ->orWhereNull('company_id')
                      ->orWhere('company_id', 1);
                });
                break;

            case 'company_only':
            case ExecutionPolicy::COMPANY_ONLY->value:
                $query->where('company_id', $companyId)
                      ->where('account_scope', 'COMPANY');
                break;

            case 'company_first':
            case ExecutionPolicy::COMPANY_FIRST->value:
                $companyAccounts = (clone $query)->where('company_id', $companyId)->get();
                if ($companyAccounts->isNotEmpty()) {
                    return $companyAccounts->sortByDesc('priority');
                }
                $query->where(function ($q) {
                    $q->where('account_scope', 'SYSTEM')
                      ->orWhereNull('company_id')
                      ->orWhere('company_id', 1);
                });
                break;

            case 'shared':
            default:
                $query->where(function ($q) use ($companyId) {
                    $q->where('company_id', $companyId)
                      ->orWhereNull('company_id')
                      ->orWhere('company_id', 1)
                      ->orWhere('account_scope', 'SYSTEM')
                      ->orWhere('account_scope', 'SHARED');
                });
                break;
        }

        $accounts = $query->orderBy('priority', 'desc')->get();

        if ($accounts->isEmpty()) {
            // المحاولة التسامحية: إعادة تعيين health_status إن كانت معطلة بسبب failed
            $fallbackQuery = AiProviderAccount::with('provider')->where('is_active', true);
            if ($executionPolicy === 'system_only' || $executionPolicy === ExecutionPolicy::SYSTEM_ONLY->value) {
                $fallbackQuery->where(function ($q) {
                    $q->where('account_scope', 'SYSTEM')->orWhereNull('company_id')->orWhere('company_id', 1);
                });
            }
            $accounts = $fallbackQuery->orderBy('priority', 'desc')->get();

            if ($accounts->isNotEmpty()) {
                foreach ($accounts as $acc) {
                    $acc->update(['health_status' => 'healthy', 'failed_attempts' => 0]);
                }
            }
        }

        // تصفية الحسابات بناءً على دعم المزود للميزة المطلوبة
        return $accounts->filter(function ($account) use ($capability) {
            return $account->provider && method_exists($account->provider, 'supportsCapability')
                ? $account->provider->supportsCapability($capability)
                : true;
        })->values();
    }

    public function reportFailure(int $accountId, string $errorCode): void
    {
        $account = AiProviderAccount::find($accountId);
        if (!$account) return;

        $account->increment('failed_attempts');
        if ($account->failed_attempts > 3) {
            $account->health_status = 'failed';
        } else {
            $account->health_status = 'degraded';
        }
        $account->save();
        Log::warning("[AI Milestone Trace] [Router Report Failure] AccountID: {$accountId}, ErrorCode: {$errorCode}");
    }

    public function reportSuccess(int $accountId, int $tokensUsed): void
    {
        $account = AiProviderAccount::find($accountId);
        if (!$account) return;

        $account->failed_attempts = 0;
        $account->health_status = 'healthy';
        $account->used_tokens_today += $tokensUsed;
        $account->used_tokens_this_month += $tokensUsed;
        $account->last_used_at = now();
        $account->save();
        Log::info("[AI Milestone Trace] [Router Report Success] AccountID: {$accountId}, TokensUsed: {$tokensUsed}");
    }
}
