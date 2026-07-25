<?php
// متحكم لإدارة مصادر الرسائل المعتمدة وتحديد المرسلين القابلين للمعالجة للوحة الويب.

namespace Modules\HwnixCash\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HwnixCash\Domain\Contracts\HwnixCashMessageSourceRepositoryInterface;
use Modules\HwnixCash\DTOs\MessageSourceData;
use Modules\HwnixCash\Http\Requests\Web\StoreMessageSourceRequest;
use Modules\HwnixCash\Http\Requests\Web\UpdateMessageSourceRequest;
use Modules\HwnixCash\Models\HwnixCashMessageSource;
use Modules\HwnixCash\Transformers\MessageSourceResource;

class MessageSourceController extends Controller
{
    public function __construct(
        protected HwnixCashMessageSourceRepositoryInterface $repository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('hwnix_cash_message_sources.view_all')) && !$user->hasPermissionTo(perm_key('hwnix_cash_message_sources.view_self'))) {
            return api_forbidden('غير مصرح لك بعرض مصادر الرسائل المعتمدة.');
        }

        $sources = $this->repository->getCompanySources($user->company_id);

        return api_success(MessageSourceResource::collection($sources), 'تم جلب قائمة مصادر الرسائل المعتمدة بنجاح.');
    }

    public function store(StoreMessageSourceRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('hwnix_cash_message_sources.create'))) {
            return api_forbidden('غير مصرح لك بإضافة مصدر رسائل جديد.');
        }

        $dto = MessageSourceData::fromArray($request->validated());
        $entity = $this->repository->create($dto, $user->company_id, $user->id);

        return api_success(new MessageSourceResource(HwnixCashMessageSource::find($entity->id)), 'تم إضافة مصدر الرسائل المعتمد بنجاح.', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $source = HwnixCashMessageSource::where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$source) {
            return api_error('مصدر الرسائل غير متوفر أو لا ينتمي لشركتك.', [], 404);
        }

        return api_success(new MessageSourceResource($source), 'تم جلب تفاصيل مصدر الرسائل بنجاح.');
    }

    public function update(UpdateMessageSourceRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('hwnix_cash_message_sources.edit_all')) && !$user->hasPermissionTo(perm_key('hwnix_cash_message_sources.edit_self'))) {
            return api_forbidden('غير مصرح لك بتعديل مصدر الرسائل.');
        }

        $source = HwnixCashMessageSource::where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$source) {
            return api_error('مصدر الرسائل غير متوفر.', [], 404);
        }

        $dto = MessageSourceData::fromArray($request->validated());
        $updatedEntity = $this->repository->update($id, $dto);

        return api_success(new MessageSourceResource(HwnixCashMessageSource::find($updatedEntity->id)), 'تم تحديث بيانات مصدر الرسائل بنجاح.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('hwnix_cash_message_sources.delete_all'))) {
            return api_forbidden('غير مصرح لك بحذف مصدر الرسائل.');
        }

        $source = HwnixCashMessageSource::where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$source) {
            return api_error('مصدر الرسائل غير متوفر.', [], 404);
        }

        $this->repository->delete($id);

        return api_success(null, 'تم حذف مصدر الرسائل المعتمد بنجاح.');
    }
}
