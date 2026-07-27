<?php

namespace Modules\AiPlatform\Engines;

use Modules\AiPlatform\Contracts\Engines\PolicyEngineInterface;
use Modules\AiPlatform\DTOs\ExecutionRequestDTO;
use Modules\AiPlatform\DTOs\PolicyDecisionDTO;
use Modules\AiPlatform\Models\AiPolicy;
use Modules\AiPlatform\Models\AiPolicyEvaluation;

/**
 * محرك فحص السياسات والشروط قبل تنفيذ الطلبات لمنع تجاوز الصلاحيات وهدر التوكينز.
 */
class PolicyEngine implements PolicyEngineInterface
{
    public function evaluate(ExecutionRequestDTO $request, ?int $userId, array $userRoles): PolicyDecisionDTO
    {
        $policies = AiPolicy::where('company_id', $request->companyId)
            ->where('agent_id', $request->agentId)
            ->where('is_active', true)
            ->orderBy('priority', 'asc')
            ->get();

        foreach ($policies as $policy) {
            $decision = $this->checkRules($policy, $request, $userId, $userRoles);
            
            if (!$decision->isAllowed) {
                $this->logEvaluation($policy, $request, $userId, $decision);
                return $decision;
            }
        }

        $allowDecision = PolicyDecisionDTO::allow();
        $this->logEvaluation(null, $request, $userId, $allowDecision);

        return $allowDecision;
    }

    private function checkRules(AiPolicy $policy, ExecutionRequestDTO $request, ?int $userId, array $userRoles): PolicyDecisionDTO
    {
        // التحقق من الشروط: rate_limit, budget, capability, role, time
        $rules = $policy->rules ?? [];

        if (!empty($rules['allowed_roles'])) {
            $hasRole = count(array_intersect($rules['allowed_roles'], $userRoles)) > 0;
            if (!$hasRole) {
                return PolicyDecisionDTO::deny('role_not_allowed', 'ليس لديك الصلاحية لاستخدام هذه الميزة.');
            }
        }

        // يمكن إضافة المزيد من التحققات هنا مثل budget أو rate_limit أو capability أو time
        
        return PolicyDecisionDTO::allow();
    }

    private function logEvaluation(?AiPolicy $policy, ExecutionRequestDTO $request, ?int $userId, PolicyDecisionDTO $decision): void
    {
        AiPolicyEvaluation::create([
            'company_id' => $request->companyId,
            'agent_id' => $request->agentId,
            'policy_id' => $policy?->id,
            'ai_policy_id' => $policy?->id ?? 1,
            'capability_key' => $request->capabilityKey,
            'user_id' => $userId,
            'request_data' => json_encode($request),
            'decision' => $decision->isAllowed() ? 'allowed' : 'denied',
            'deny_reason' => $decision->denyReason,
            'deny_message' => $decision->denyMessage,
        ]);
    }
}
