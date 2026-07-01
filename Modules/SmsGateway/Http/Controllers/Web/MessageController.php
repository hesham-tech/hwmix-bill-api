<?php
// متحكم لإدارة رسائل SMS وعرض سجلات الإرسال وبث رسائل جديدة للعملاء.

namespace Modules\SmsGateway\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SmsGateway\Domain\Contracts\SmsMessageRepositoryInterface;
use Modules\SmsGateway\Services\SmsDispatcherService;
use Modules\SmsGateway\Models\SmsMessage;

class MessageController extends Controller
{
    public function __construct(
        protected SmsMessageRepositoryInterface $messageRepo,
        protected SmsDispatcherService $dispatcherService
    ) {}

    /**
     * عرض جميع الرسائل التابعة للشركة مع الفلترة والـ Pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('sms_messages.view_all')) && !$user->hasPermissionTo(perm_key('sms_messages.view_self'))) {
            return api_forbidden('غير مصرح لك بعرض الرسائل.');
        }

        // بناء الاستعلام مع العلاقات لتحسين الأداء (Eager Loading)
        $query = SmsMessage::with(['device', 'line'])
            ->where('company_id', $user->active_company_id);

        // الفلاتر
        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('device_id')) {
            $query->where('sms_device_id', $request->device_id);
        }

        // التصفية للمستخدم العادي (رؤية رسائله فقط)
        if (!$user->hasPermissionTo(perm_key('sms_messages.view_all'))) {
            $query->where('created_by', $user->id);
        }

        $messages = $query->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 20);

        // تنسيق الـ Pagination واستجابة الـ Resource
        $formatted = $messages->getCollection()->map(fn($msg) => [
            'id' => $msg->id,
            'phone_number' => $msg->phone_number,
            'message_body' => $msg->message_body,
            'direction' => $msg->direction,
            'status' => $msg->status,
            'failure_reason' => $msg->failure_reason,
            'retry_count' => $msg->retry_count,
            'sent_at' => $msg->sent_at?->format('Y-m-d H:i:s'),
            'delivered_at' => $msg->delivered_at?->format('Y-m-d H:i:s'),
            'created_at' => $msg->created_at?->format('Y-m-d H:i:s'),
            'device' => [
                'id' => $msg->device?->id,
                'device_name' => $msg->device?->device_name,
            ],
            'line' => [
                'id' => $msg->line?->id,
                'carrier' => $msg->line?->carrier,
                'phone_number' => $msg->line?->phone_number,
            ]
        ]);

        return api_success([
            'items' => $formatted,
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ]
        ], 'تم جلب قائمة الرسائل بنجاح.');
    }

    /**
     * إرسال رسالة SMS جديدة (وضعها في طابور الجهاز المطلوب).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('sms_messages.create'))) {
            return api_forbidden('غير مصرح لك بإرسال رسائل.');
        }

        $validated = $request->validate([
            'sms_line_id' => 'required|integer',
            'phone_number' => 'required|string',
            'message_body' => 'required|string',
        ]);

        // معالجة الإرسال عبر محرك الـ dispatch
        $messageEntity = $this->dispatcherService->dispatchOutgoingSms($validated, $user->active_company_id, $user->id);

        return api_success([
            'message_id' => $messageEntity->id,
            'status' => $messageEntity->status->value,
        ], 'تم جدولة إرسال الرسالة بنجاح.');
    }
}
