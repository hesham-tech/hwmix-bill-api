<?php
// متحكم لإدارة رسائل كاش هونكس HwnixCash وعرض سجلات الإرسال والترسيل.

namespace Modules\HwnixCash\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HwnixCash\Actions\DispatchOutgoingSmsAction;
use Modules\HwnixCash\DTOs\OutgoingSmsData;
use Modules\HwnixCash\Http\Requests\Web\StoreMessageRequest;
use Modules\HwnixCash\Models\HwnixCashMessage;
use Modules\HwnixCash\Transformers\SmsMessageResource;

class MessageController extends Controller
{
    public function __construct(
        protected DispatchOutgoingSmsAction $dispatchAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->active_company_id ?? $user->company_id;

        $hasAccess = true;
        try {
            $hasAccess = $user->can(perm_key('admin.super'))
                || $user->can(perm_key('admin.company'))
                || $user->can(perm_key('hwnix_cash_messages.view_all'))
                || $user->can(perm_key('hwnix_cash_messages.view_self'))
                || $user->hasCapability('is_internal', $companyId);
        } catch (\Throwable $e) {
            $hasAccess = true;
        }

        if (!$hasAccess) {
            return api_forbidden('غير مصرح لك بعرض الرسائل.');
        }

        $query = HwnixCashMessage::with(['device', 'line'])
            ->where('company_id', $companyId);

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('device_id')) {
            $query->where('sms_device_id', $request->device_id);
        }

        $messages = $query->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 20);

        return api_success(SmsMessageResource::collection($messages), 'تم جلب قائمة الرسائل بنجاح.');
    }

    public function store(StoreMessageRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('hwnix_cash_messages.create'))) {
            return api_forbidden('غير مصرح لك بإرسال رسائل.');
        }

        $dto = OutgoingSmsData::fromArray($request->validated());
        $messageEntity = $this->dispatchAction->execute($dto, $user->company_id, $user->id);

        return api_success([
            'message_id' => $messageEntity->id,
            'status' => $messageEntity->status->value,
        ], 'تم جدولة إرسال الرسالة بنجاح.');
    }

    public function reparse(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->active_company_id ?? $user->company_id;

        $messageModel = HwnixCashMessage::where('company_id', $companyId)->findOrFail($id);

        $dto = new \Modules\HwnixCash\Domain\Entities\SmsMessage(
            id: $messageModel->id,
            companyId: $messageModel->company_id,
            createdBy: $messageModel->created_by,
            smsDeviceId: $messageModel->sms_device_id,
            smsLineId: $messageModel->sms_line_id,
            phoneNumber: $messageModel->phone_number,
            messageBody: $messageModel->message_body,
            direction: $messageModel->direction,
            status: $messageModel->status instanceof \Modules\HwnixCash\Domain\Enums\SmsMessageStatus ? $messageModel->status : \Modules\HwnixCash\Domain\Enums\SmsMessageStatus::from($messageModel->status),
            messageRef: $messageModel->message_ref,
            errorCode: $messageModel->error_code,
            errorMessage: $messageModel->error_message,
            sentAt: $messageModel->sent_at?->toIso8601String()
        );

        app(\Modules\HwnixCash\Domain\Contracts\HwnixCashMessageParserInterface::class)->parse($dto);

        $fresh = $messageModel->fresh();

        return api_success(
            new SmsMessageResource($fresh),
            'تمت إعادة تحليل الرسالة وتحديث البيانات المالية بنجاح.'
        );
    }
}
