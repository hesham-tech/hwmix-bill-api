<?php

namespace Modules\Guidance\Http\Controllers;

/**
 * متحكم لإدارة تقدم الإرشادات والجولات التعريفية والتلميحات للمستخدم (Multi-Tenant API).
 */

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Guidance\Services\GuidanceService;
use Modules\Guidance\Http\Requests\CompleteStepRequest;
use Modules\Guidance\Transformers\GuidanceProgressResource;
use Illuminate\Support\Facades\Log;

class GuidanceController extends Controller
{
    protected GuidanceService $guidanceService;

    /**
     * حقن خدمة الإرشاد.
     */
    public function __construct(GuidanceService $guidanceService)
    {
        $this->guidanceService = $guidanceService;
    }

    /**
     * جلب تقدم الإرشادات والجولات التعريفية للمستخدم الحالي والشركة النشطة.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $companyId = $user->active_company_id;

            if (!$companyId) {
                return api_success([], 'تم جلب تقدم الإرشادات بنجاح (وضع كل الشركات).');
            }

            $progress = $this->guidanceService->getProgressForUser($user, $companyId);
            
            return api_success(
                GuidanceProgressResource::collection($progress),
                'تم جلب تقدم الإرشادات بنجاح.'
            );
        } catch (\Throwable $e) {
            Log::error('Error fetching guidance progress: ' . $e->getMessage());
            return api_exception($e, 500);
        }
    }

    /**
     * تسجيل إكمال أو تخطي خطوة أو جولة إرشادية.
     */
    public function complete(CompleteStepRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $companyId = $user->active_company_id;

            if (!$companyId) {
                return api_success((object)[], 'تم تسجيل خطوة الإرشاد بنجاح (وضع كل الشركات).');
            }

            $validated = $request->validated();
            $key = $validated['key'];
            $skipped = (bool) ($validated['skipped'] ?? false);

            $progress = $this->guidanceService->completeStep($user, $companyId, $key, $skipped);

            return api_success(
                new GuidanceProgressResource($progress),
                'تم تسجيل خطوة الإرشاد بنجاح.'
            );
        } catch (\Throwable $e) {
            Log::error('Error completing guidance step: ' . $e->getMessage());
            return api_exception($e, 500);
        }
    }

    /**
     * إلغاء تسجيل خطوة أو جولة إرشادية (التراجع عن الإكمال).
     */
    public function uncomplete(CompleteStepRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $companyId = $user->active_company_id;

            if (!$companyId) {
                return api_success(null, 'تم إلغاء إكمال خطوة الإرشاد بنجاح (وضع كل الشركات).');
            }

            $validated = $request->validated();
            $key = $validated['key'];

            $this->guidanceService->uncompleteStep($user, $companyId, $key);

            return api_success(
                null,
                'تم إلغاء إكمال خطوة الإرشاد بنجاح.'
            );
        } catch (\Throwable $e) {
            Log::error('Error uncompleting guidance step: ' . $e->getMessage());
            return api_exception($e, 500);
        }
    }

    /**
     * إعادة تعيين كافة تقدم الإرشادات والجولات للمستخدم الحالي لاستعادتها.
     */
    public function reset(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $companyId = $user->active_company_id;

            if (!$companyId) {
                return api_success(null, 'تم إعادة ضبط جولات الإرشاد والتعليم بنجاح (وضع كل الشركات).');
            }

            $this->guidanceService->resetProgressForUser($user, $companyId);

            return api_success(
                null,
                'تم إعادة ضبط جولات الإرشاد والتعليم بنجاح.'
            );
        } catch (\Throwable $e) {
            Log::error('Error resetting guidance progress: ' . $e->getMessage());
            return api_exception($e, 500);
        }
    }
}

