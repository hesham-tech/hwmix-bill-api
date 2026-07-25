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
        if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('hwnix_cash_messages.view_all')) && !$user->hasPermissionTo(perm_key('hwnix_cash_messages.view_self'))) {
            return api_forbidden('غير مصرح لك بعرض الرسائل.');
        }

        $query = HwnixCashMessage::with(['device', 'line'])
            ->where('company_id', $user->company_id);

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('device_id')) {
            $query->where('sms_device_id', $request->device_id);
        }

        if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('hwnix_cash_messages.view_all'))) {
            $query->where('created_by', $user->id);
        }

        $messages = $query->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 20);

        return api_success([
            'items' => SmsMessageResource::collection($messages->getCollection()),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ]
        ], 'تم جلب قائمة الرسائل بنجاح.');
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
}
