<?php

namespace Modules\AiPlatform\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiPlatform\Models\AiProvider;
use Modules\AiPlatform\Models\AiProviderAccount;
use Modules\AiPlatform\Models\AiAgent;
use Modules\AiPlatform\Models\AiPrompt;
use Modules\AiPlatform\Models\AiUsageLog;
use Modules\AiPlatform\Models\AiCapability;

class DashboardApiController extends Controller
{
    /**
     * إحصائيات وملخص لوحة التحكم لفرونت إند Vue.js
     */
    public function stats(Request $request): JsonResponse
    {
        $companyId = $request->get('company_id', 1);

        return response()->json([
            'success' => true,
            'data'    => [
                'providers_count'    => AiProvider::where('is_active', true)->count(),
                'accounts_count'     => AiProviderAccount::where('company_id', $companyId)->where('is_active', true)->count(),
                'capabilities_count' => AiCapability::where('is_active', true)->count(),
                'agents_count'       => AiAgent::where('company_id', $companyId)->where('is_active', true)->count(),
                'prompts_count'      => AiPrompt::where('company_id', $companyId)->where('is_active', true)->count(),
                'total_usage_cost'   => (float) AiUsageLog::where('company_id', $companyId)->sum('total_cost'),
                'total_tokens_used'  => (int) AiUsageLog::where('company_id', $companyId)->sum('total_tokens'),
            ],
        ]);
    }

    /**
     * قائمة المزودين والحسابات الخاصة بالشركة
     */
    public function providers(Request $request): JsonResponse
    {
        $companyId = $request->get('company_id', 1);

        $providers = AiProvider::with(['accounts' => function($q) use ($companyId) {
            $q->where('company_id', $companyId);
        }])->get();

        return response()->json([
            'success' => true,
            'data'    => $providers,
        ]);
    }

    /**
     * قائمة الوكلاء الذكيين
     */
    public function agents(Request $request): JsonResponse
    {
        $companyId = $request->get('company_id', 1);
        $agents    = AiAgent::where('company_id', $companyId)->get();

        return response()->json([
            'success' => true,
            'data'    => $agents,
        ]);
    }

    /**
     * قائمة قوالب الـ Prompts
     */
    public function prompts(Request $request): JsonResponse
    {
        $companyId = $request->get('company_id', 1);
        $prompts   = AiPrompt::where('company_id', $companyId)->orWhereNull('company_id')->get();

        return response()->json([
            'success' => true,
            'data'    => $prompts,
        ]);
    }

    /**
     * تقرير الاستهلاك والتكاليف للوحة التحكم
     */
    public function usageReport(Request $request): JsonResponse
    {
        $companyId = $request->get('company_id', 1);
        $logs      = AiUsageLog::where('company_id', $companyId)
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $logs,
        ]);
    }

    /**
     * قائمة جميع نماذج الذكاء الاصطناعي المسجلة
     */
    public function models(Request $request): JsonResponse
    {
        $models = \Modules\AiPlatform\Models\AiModel::with('provider')->get();

        return response()->json([
            'success' => true,
            'data'    => $models,
        ]);
    }

    /**
     * إضافة نموذج ذكاء اصطناعي جديد
     */
    public function storeModel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ai_provider_id'      => 'required|exists:ai_providers,id',
            'model_id'            => 'required|string|max:100',
            'label'               => 'required|string|max:255',
            'version'             => 'nullable|string',
            'max_context_tokens'  => 'nullable|integer',
            'max_output_tokens'   => 'nullable|integer',
            'input_price_per_1k'  => 'nullable|numeric',
            'output_price_per_1k' => 'nullable|numeric',
        ]);

        $model = \Modules\AiPlatform\Models\AiModel::create(array_merge($validated, [
            'is_active' => true,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة النموذج بنجاح',
            'data'    => $model,
        ], 201);
    }

    /**
     * تحديث بيانات النموذج
     */
    public function updateModel(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'ai_provider_id'      => 'sometimes|exists:ai_providers,id',
            'model_id'            => 'sometimes|string|max:100',
            'label'               => 'sometimes|string|max:255',
            'version'             => 'nullable|string',
            'max_context_tokens'  => 'nullable|integer',
            'max_output_tokens'   => 'nullable|integer',
            'input_price_per_1k'  => 'nullable|numeric',
            'output_price_per_1k' => 'nullable|numeric',
        ]);

        $model = \Modules\AiPlatform\Models\AiModel::findOrFail($id);
        $model->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث النموذج بنجاح',
            'data'    => $model,
        ]);
    }

    /**
     * حذف النموذج
     */
    public function destroyModel($id): JsonResponse
    {
        $model = \Modules\AiPlatform\Models\AiModel::findOrFail($id);
        $model->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف النموذج بنجاح',
        ]);
    }

    /**
     * تغيير حالة تفعيل النموذج
     */
    public function toggleModelActive($id): JsonResponse
    {
        $model = \Modules\AiPlatform\Models\AiModel::findOrFail($id);
        $model->is_active = !$model->is_active;
        $model->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير حالة النموذج بنجاح',
            'data'    => $model,
        ]);
    }

    /**
     * قائمة جميع حسابات ومفاتيح المزودين للشركة
     */
    public function accounts(Request $request): JsonResponse
    {
        $companyId = $request->get('company_id', 1);
        $accounts  = AiProviderAccount::with('provider')
            ->where('company_id', $companyId)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $accounts,
        ]);
    }

    /**
     * إضافة حساب مزود جديد (API Key)
     */
    public function storeAccount(Request $request): JsonResponse
    {
        $isSystem  = $request->boolean('is_system', false);
        $companyId = $isSystem ? null : $request->get('company_id', 1);

        $validated = $request->validate([
            'ai_provider_id'    => 'required|exists:ai_providers,id',
            'label'             => 'required|string|max:255',
            'api_key_encrypted' => 'required|string',
            'custom_base_url'   => 'nullable|url',
            'priority'          => 'nullable|integer',
        ]);

        /** @var \Modules\AiPlatform\Contracts\Security\SecretVaultInterface $vault */
        $vault = app(\Modules\AiPlatform\Contracts\Security\SecretVaultInterface::class);
        $rawKey = $validated['api_key_encrypted'];

        $account = AiProviderAccount::create([
            'company_id'        => $companyId,
            'ai_provider_id'    => $validated['ai_provider_id'],
            'label'             => $validated['label'],
            'api_key_encrypted' => $vault->encrypt($rawKey),
            'api_key_hint'      => $vault->hint($rawKey),
            'custom_base_url'   => $validated['custom_base_url'] ?? null,
            'priority'          => $validated['priority'] ?? 1,
            'is_active'         => true,
            'health_status'     => 'healthy',
        ]);

        $account->load('provider');

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة حساب المزود والمفتاح بنجاح',
            'data'    => $account,
        ], 201);
    }

    /**
     * تحديث بيانات حساب المزود / المفتاح
     */
    public function updateAccount(Request $request, $id): JsonResponse
    {
        $isSystem  = $request->boolean('is_system', false);
        $companyId = $isSystem ? null : $request->get('company_id', 1);
        $account   = AiProviderAccount::where(function ($q) use ($companyId) {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        })->findOrFail($id);

        $validated = $request->validate([
            'ai_provider_id'    => 'sometimes|exists:ai_providers,id',
            'label'             => 'sometimes|string|max:255',
            'api_key_encrypted' => 'nullable|string',
            'custom_base_url'   => 'nullable|url',
            'priority'          => 'nullable|integer',
        ]);

        if ($request->has('is_system')) {
            $validated['company_id'] = $companyId;
        }

        if (!empty($validated['api_key_encrypted'])) {
            /** @var \Modules\AiPlatform\Contracts\Security\SecretVaultInterface $vault */
            $vault = app(\Modules\AiPlatform\Contracts\Security\SecretVaultInterface::class);
            $rawKey = $validated['api_key_encrypted'];
            $validated['api_key_encrypted'] = $vault->encrypt($rawKey);
            $validated['api_key_hint']      = $vault->hint($rawKey);
        } else {
            unset($validated['api_key_encrypted']);
        }

        $account->update($validated);
        $account->load('provider');

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حساب المزود بنجاح',
            'data'    => $account,
        ]);
    }

    /**
     * حذف حساب المزود
     */
    public function destroyAccount(Request $request, $id): JsonResponse
    {
        $companyId = $request->get('company_id', 1);
        $account   = AiProviderAccount::where('company_id', $companyId)->findOrFail($id);
        $account->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف حساب المزود بنجاح',
        ]);
    }

    /**
     * تفعيل أو تعطيل حساب المزود
     */
    public function toggleAccountActive(Request $request, $id): JsonResponse
    {
        $companyId = $request->get('company_id', 1);
        $account   = AiProviderAccount::where('company_id', $companyId)->findOrFail($id);
        $account->is_active = !$account->is_active;
        $account->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير حالة حساب المزود بنجاح',
            'data'    => $account,
        ]);
    }

    /* ==================== Agents CRUD ==================== */

    public function storeAgent(Request $request): JsonResponse
    {
        $companyId = $request->get('company_id', 1);
        $request->validate([
            'key'  => 'required|string|max:100',
            'name' => 'nullable|string|max:255',
            'label'=> 'nullable|string|max:255',
        ]);

        $label = $request->input('label') ?? $request->input('name') ?? $request->input('key');

        $agent = AiAgent::create([
            'company_id'           => $companyId,
            'key'                  => $request->input('key'),
            'label'                => $label,
            'description'          => $request->input('description'),
            'system_prompt'        => $request->input('system_prompt'),
            'preferred_capability' => $request->input('preferred_capability', 'text.generate'),
            'is_active'            => true,
            'memory_enabled'       => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الوكيل الذكي بنجاح',
            'data'    => $agent,
        ], 201);
    }

    public function updateAgent(Request $request, $id): JsonResponse
    {
        $companyId = $request->get('company_id', 1);
        $agent     = AiAgent::where('company_id', $companyId)->findOrFail($id);

        $data = $request->only(['key', 'description', 'system_prompt', 'preferred_capability', 'is_active']);
        if ($request->has('label') || $request->has('name')) {
            $data['label'] = $request->input('label') ?? $request->input('name');
        }

        $agent->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الوكيل الذكي بنجاح',
            'data'    => $agent,
        ]);
    }

    public function destroyAgent(Request $request, $id): JsonResponse
    {
        $companyId = $request->get('company_id', 1);
        $agent     = AiAgent::where('company_id', $companyId)->findOrFail($id);
        $agent->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الوكيل الذكي بنجاح',
        ]);
    }

    public function toggleAgentActive(Request $request, $id): JsonResponse
    {
        $companyId = $request->get('company_id', 1);
        $agent     = AiAgent::where('company_id', $companyId)->findOrFail($id);
        $agent->is_active = !$agent->is_active;
        $agent->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير حالة الوكيل الذكي بنجاح',
            'data'    => $agent,
        ]);
    }

    /* ==================== Prompts CRUD ==================== */

    public function storePrompt(Request $request): JsonResponse
    {
        $companyId = $request->get('company_id', 1);
        $validated = $request->validate([
            'key'               => 'required|string|max:100',
            'title'             => 'required|string|max:255',
            'description'       => 'nullable|string',
            'capability_key'    => 'nullable|string',
            'template'          => 'required|string',
        ]);

        $prompt = AiPrompt::create([
            'company_id'     => $companyId,
            'key'            => $validated['key'],
            'title'          => $validated['title'],
            'description'    => $validated['description'] ?? null,
            'capability_key' => $validated['capability_key'] ?? 'text.generate',
            'is_active'      => true,
        ]);

        // إضافة نسخة الـ Prompt الأولى
        \Illuminate\Support\Facades\DB::table('ai_prompt_versions')->insert([
            'ai_prompt_id' => $prompt->id,
            'company_id'   => $companyId,
            'version'      => 1,
            'locale'       => 'ar',
            'template'     => $validated['template'],
            'notes'        => 'الإصدار الأول',
            'is_active'    => true,
            'created_at'   => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة قالب الـ Prompt بنجاح',
            'data'    => $prompt,
        ], 201);
    }

    public function updatePrompt(Request $request, $id): JsonResponse
    {
        $companyId = $request->get('company_id', 1);
        $prompt    = AiPrompt::where('company_id', $companyId)->orWhereNull('company_id')->findOrFail($id);

        $validated = $request->validate([
            'title'          => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'capability_key' => 'nullable|string',
            'template'       => 'nullable|string',
        ]);

        $prompt->update($validated);

        if (!empty($validated['template'])) {
            $latestVersion = \Illuminate\Support\Facades\DB::table('ai_prompt_versions')
                ->where('ai_prompt_id', $prompt->id)
                ->max('version') ?? 0;

            \Illuminate\Support\Facades\DB::table('ai_prompt_versions')->insert([
                'ai_prompt_id' => $prompt->id,
                'company_id'   => $companyId,
                'version'      => $latestVersion + 1,
                'locale'       => 'ar',
                'template'     => $validated['template'],
                'notes'        => 'تحديث جديد',
                'is_active'    => true,
                'created_at'   => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث قالب الـ Prompt بنجاح',
            'data'    => $prompt,
        ]);
    }

    public function destroyPrompt(Request $request, $id): JsonResponse
    {
        $companyId = $request->get('company_id', 1);
        $prompt    = AiPrompt::where('company_id', $companyId)->orWhereNull('company_id')->findOrFail($id);
        $prompt->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف القالب بنجاح',
        ]);
    }

    public function togglePromptActive(Request $request, $id): JsonResponse
    {
        $companyId = $request->get('company_id', 1);
        $prompt    = AiPrompt::where('company_id', $companyId)->orWhereNull('company_id')->findOrFail($id);
        $prompt->is_active = !$prompt->is_active;
        $prompt->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير حالة القالب بنجاح',
            'data'    => $prompt,
        ]);
    }
}
