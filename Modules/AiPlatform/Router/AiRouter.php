<?php

namespace Modules\AiPlatform\Router;

use Modules\AiPlatform\Contracts\Router\AiRouterInterface;
use Modules\AiPlatform\DTOs\RouterSelectionDTO;
use Modules\AiPlatform\Models\AiProviderAccount;
use Modules\AiPlatform\Models\AiRouterLog;
use Modules\AiPlatform\Enums\Capability;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * محرك التوجيه الذكي لاختيار الحساب والنموذج المناسب للطلبات وإدارة التوزيع والـ Failover
 */
class AiRouter implements AiRouterInterface
{
    public function select(Capability $capability, int $companyId, array $requirements, string $strategy): RouterSelectionDTO
    {
        $startTime = microtime(true);
        Log::info("[AI Milestone Trace] [Router Enter] Capability: " . ($capability->value ?? $capability) . ", CompanyID: {$companyId}");

        // 1. جلب الحسابات المتاحة للشركة أو الحسابات العامة المتاحة للنظام
        $accounts = AiProviderAccount::with('provider')
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                  ->orWhereNull('company_id')
                  ->orWhere('company_id', 1);
            })
            ->where('is_active', true)
            ->where('health_status', '!=', 'failed')
            ->get();

        if ($accounts->isEmpty()) {
            // المحاولة التسامحية: إعادة تعيين health_status إن كانت معطلة بسبب failed
            $accounts = AiProviderAccount::with('provider')
                ->where('is_active', true)
                ->get();
                
            if ($accounts->isNotEmpty()) {
                foreach ($accounts as $acc) {
                    $acc->update(['health_status' => 'healthy', 'failed_attempts' => 0]);
                }
            } else {
                Log::error("[AI Milestone Trace] [Router Failure] No active accounts found.");
                throw new Exception("لا يوجد حساب مفاتيح ذكاء اصطناعي (API Key) مفعل متاح للنظام. يرجى تفعيل مفتاح في شاشة مفاتيح وحسابات API.");
            }
        }

        // 2. تصفية الحسابات بناءً على دعم المزود للميزة المطلوبة
        $supportedAccounts = $accounts->filter(function ($account) use ($capability) {
            return $account->provider && method_exists($account->provider, 'supportsCapability') 
                ? $account->provider->supportsCapability($capability) 
                : true;
        });

        if ($supportedAccounts->isEmpty()) {
            Log::error("[AI Milestone Trace] [Router Failure] No supported accounts.");
            throw new Exception("No active AI provider accounts support the required capability.");
        }

        // 3. ترتيب الحسابات بناءً على الاستراتيجية
        switch ($strategy) {
            case 'cost':
                $selectedAccount = $supportedAccounts->sortBy(function ($account) {
                    return $account->provider->cost_per_1k_tokens ?? 999999;
                })->first();
                break;

            case 'quality':
                $selectedAccount = $supportedAccounts->sortByDesc(function ($account) {
                    return $account->provider->quality_score ?? 0;
                })->first();
                break;

            case 'priority':
            default:
                $selectedAccount = $supportedAccounts->sortByDesc('priority')->first();
                break;
        }

        if (!$selectedAccount) {
            Log::error("[AI Milestone Trace] [Router Failure] Selection strategy failed.");
            throw new Exception("Failed to select an AI provider account based on the requested strategy.");
        }

        $activeModel = \Modules\AiPlatform\Models\AiModel::where('ai_provider_id', $selectedAccount->ai_provider_id)
            ->where('is_active', true)
            ->first();

        $selectedModelSlug = $activeModel?->model_id ?? 'gemini-1.5-flash';

        // 4. تسجيل القرار في جدول ai_router_logs
        $log = AiRouterLog::create([
            'company_id' => $companyId,
            'ai_provider_account_id' => $selectedAccount->id,
            'capability' => $capability->value ?? (string) $capability,
            'capability_key' => is_object($capability) ? $capability->value : $capability,
            'strategy' => $strategy,
            'requirements' => json_encode($requirements),
            'status' => 'selected',
        ]);

        $decisionMs = (int) ((microtime(true) - $startTime) * 1000);
        Log::info("[AI Milestone Trace] [Router Exit] Selected AccountID: {$selectedAccount->id}, ModelSlug: {$selectedModelSlug}, DecisionTime: {$decisionMs}ms");

        // 5. إرجاع الـ DTO
        return new RouterSelectionDTO(
            accountId: $selectedAccount->id,
            modelId: $selectedModelSlug,
            reason: $strategy,
            consideredAccounts: $accounts->pluck('id')->toArray(),
            decisionMs: $decisionMs
        );
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
