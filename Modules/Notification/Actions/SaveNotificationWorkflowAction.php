<?php

namespace Modules\Notification\Actions;

// تعليق عربي: أكشن لحفظ أو تحديث قواعد أتمتة الإشعارات (Workflows) مع خطواتها المجدولة لكل شركة بشكل مستقل ومحمي داخل DB Transaction.

use Modules\Core\Actions\BaseAction;
use Modules\Notification\Models\NotificationWorkflow;
use Modules\Notification\Models\NotificationWorkflowStep;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaveNotificationWorkflowAction extends BaseAction
{
    /**
     * تنفيذ الأكشن لحفظ أو تحديث قاعدة الأتمتة وخطواتها.
     */
    public function handle(array $data = []): NotificationWorkflow
    {
        $this->authorize('companies.update_self');

        $companyId = Auth::user()->active_company_id;
        $id = $data['id'] ?? null;

        // التحقق من توافر قنوات الاتصال والربط النشطة للشركة أو النظام العام
        $hasEmail = \Modules\Notification\Models\MailSetting::where(function ($query) use ($companyId) {
            $query->where('company_id', $companyId)
                  ->orWhere('is_global', true);
        })
        ->where('is_active', true)
        ->exists();

        $hasWhatsApp = \Modules\Notification\Models\WhatsAppSetting::where(function ($query) use ($companyId) {
            $query->where('company_id', $companyId)
                  ->orWhere('is_global', true);
        })
        ->where('is_active', true)
        ->exists();

        // 1. إذا تم طلب تفعيل القاعدة بالكامل
        if (isset($data['is_active']) && (bool)$data['is_active']) {
            if (!$hasEmail && !$hasWhatsApp) {
                throw new \Exception('لا يمكن تفعيل قاعدة الأتمتة لعدم وجود قنوات اتصال مفعلة ونشطة للشركة (بريد إلكتروني أو واتساب). يرجى تهيئة الخدمات أولاً.');
            }
        }

        // 2. التحقق من قنوات خطوات الإرسال (channel الآن array مثل ['email', 'whatsapp'])
        if (isset($data['steps']) && is_array($data['steps'])) {
            foreach ($data['steps'] as $index => $stepData) {
                $stepActive = isset($stepData['is_active']) ? (bool)$stepData['is_active'] : true;
                if ($stepActive) {
                    $stepNum = $stepData['step_number'] ?? ($index + 1);

                    // تحويل القيمة إلى مصفوفة في جميع الأحوال
                    $channels = $stepData['channel'] ?? [];
                    if (is_string($channels)) {
                        $channels = [$channels];
                    }
                    $channels = array_filter($channels);

                    if (empty($channels)) {
                        throw new \Exception("الخطوة رقم {$stepNum}: يجب اختيار قناة إرسال واحدة على الأقل.");
                    }

                    if (in_array('email', $channels) && !$hasEmail) {
                        throw new \Exception("الخطوة رقم {$stepNum}: لا يمكن تفعيل قناة البريد الإلكتروني لعدم تهيئة الخدمة للشركة.");
                    }
                    if (in_array('whatsapp', $channels) && !$hasWhatsApp) {
                        throw new \Exception("الخطوة رقم {$stepNum}: لا يمكن تفعيل قناة الواتساب لعدم تهيئة الخدمة للشركة.");
                    }
                }
            }
        }

        return DB::transaction(function () use ($companyId, $id, $data) {
            $workflowData = [
                'event_type' => $data['event_type'],
                'is_active' => isset($data['is_active']) ? (bool)$data['is_active'] : false,
                'company_id' => $companyId,
            ];

            if (isset($data['is_global']) && Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                $workflowData['is_global'] = (bool)$data['is_global'];
            }

            if ($id) {
                if (Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                    $workflow = NotificationWorkflow::findOrFail($id);
                } else {
                    $workflow = NotificationWorkflow::where('company_id', $companyId)->findOrFail($id);
                }
                $workflow->update($workflowData);
            } else {
                // منع التكرار لنفس الحدث لنفس الشركة وتأمين الربط
                if (isset($workflowData['is_global']) && $workflowData['is_global']) {
                    $workflow = NotificationWorkflow::firstOrCreate(
                        ['is_global' => true, 'event_type' => $data['event_type']],
                        array_merge($workflowData, ['company_id' => $companyId])
                    );
                } else {
                    $workflow = NotificationWorkflow::firstOrCreate(
                        ['company_id' => $companyId, 'event_type' => $data['event_type']],
                        ['is_active' => $workflowData['is_active']]
                    );
                }
                if (!$workflow->wasRecentlyCreated) {
                    $workflow->update($workflowData);
                }
            }

            // تحديث أو إعادة بناء الخطوات التابعة للقاعدة
            $incomingStepIds = [];
            foreach ($data['steps'] as $index => $stepData) {
                $stepNumber = $stepData['step_number'] ?? ($index + 1);

                $stepPayload = [
                    'step_number' => $stepNumber,
                    'delay_days' => (int) $stepData['delay_days'],
                    'channel' => is_array($stepData['channel']) ? $stepData['channel'] : [$stepData['channel']],
                    'template_id' => $stepData['template_id'],
                    'is_active' => isset($stepData['is_active']) ? (bool)$stepData['is_active'] : true,
                ];

                $stepId = $stepData['id'] ?? null;

                if ($stepId) {
                    $step = NotificationWorkflowStep::where('workflow_id', $workflow->id)->findOrFail($stepId);
                    $step->update($stepPayload);
                } else {
                    $step = $workflow->steps()->create($stepPayload);
                }

                $incomingStepIds[] = $step->id;
            }

            // حذف الخطوات التي لم تعد مرسلة في طلب التحديث للحفاظ على النظافة والاتساق
            $workflow->steps()->whereNotIn('id', $incomingStepIds)->delete();

            return $workflow->load('steps.template');
        });
    }
}
