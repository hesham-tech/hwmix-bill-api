<?php

namespace Modules\Notification\Http\Controllers;

//   متحكم لإدارة قوالب الإشعارات (إضافة، تعديل، عرض، وحذف) للشركة الحالية أو العامة مع التصفية التلقائية.

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Notification\Actions\SaveNotificationTemplateAction;
use Modules\Notification\Http\Requests\NotificationTemplateRequest;
use Modules\Notification\Http\Resources\NotificationTemplateResource;
use Modules\Notification\Models\NotificationTemplate;

class NotificationTemplateController extends Controller
{
    /**
     * عرض قائمة القوالب المضافة للشركة الحالية أو العامة.
     */
    public function index(): JsonResponse
    {
        try {
            $templates = NotificationTemplate::get();
            return api_success(NotificationTemplateResource::collection($templates), 'تم جلب قوالب الإشعارات بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض تفاصيل قالب معين.
     */
    public function show($id): JsonResponse
    {
        try {
            $template = NotificationTemplate::findOrFail($id);
            return api_success(new NotificationTemplateResource($template), 'تم جلب تفاصيل القالب بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إضافة قالب إشعارات جديد للشركة.
     */
    public function store(NotificationTemplateRequest $request, SaveNotificationTemplateAction $action): JsonResponse
    {
        try {
            $template = $action->handle($request->validated());
            return api_success(new NotificationTemplateResource($template), 'تم إضافة قالب الإشعار بنجاح', 201);
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تعديل قالب إشعارات حالي بالمعرف.
     */
    public function update(NotificationTemplateRequest $request, $id, SaveNotificationTemplateAction $action): JsonResponse
    {
        try {
            $template = NotificationTemplate::findOrFail($id);
            if ($template->is_global && (!Auth::user() || !Auth::user()->hasPermissionTo(perm_key('admin.super')))) {
                return api_error('غير مسموح بتعديل السجلات العامة للسيستم.', [], 403);
            }

            $data = array_merge($request->validated(), ['id' => $id]);
            $template = $action->handle($data);
            return api_success(new NotificationTemplateResource($template), 'تم تحديث قالب الإشعار بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف قالب معين.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $template = NotificationTemplate::findOrFail($id);
            if ($template->is_global && (!Auth::user() || !Auth::user()->hasPermissionTo(perm_key('admin.super')))) {
                return api_error('غير مسموح بحذف السجلات العامة للسيستم.', [], 403);
            }

            $template->delete();
            return api_success(null, 'تم حذف قالب الإشعار بنجاح');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }
}
