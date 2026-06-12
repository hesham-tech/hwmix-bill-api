<?php

namespace Modules\Notification\Jobs;

//   وظيفة خلفية (Job) لإرسال تنبيهات الواتساب بشكل غير متزامن باستخدام WhatsApp Cloud API.

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Notification\Models\NotificationLog;
use Modules\Notification\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;

class SendWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $companyId;
    protected ?int $branchId;
    protected string $recipient;
    protected string $messageOrTemplate;
    protected array $components;
    protected string $type;
    protected array $gatewayConfig;
    protected ?int $createdBy;

    public function __construct(
        int $companyId,
        ?int $branchId,
        string $recipient,
        string $messageOrTemplate,
        array $components = [],
        string $type = 'text',
        array $gatewayConfig = [],
        ?int $createdBy = null
    ) {
        $this->companyId = $companyId;
        $this->branchId = $branchId;
        $this->recipient = $recipient;
        $this->messageOrTemplate = $messageOrTemplate;
        $this->components = $components;
        $this->type = $type;
        $this->gatewayConfig = $gatewayConfig;
        $this->createdBy = $createdBy;
    }

    public function handle(): void
    {
        try {
            // إعداد الخدمة بناءً على الإعدادات المخصصة للشركة أو الافتراضية
            $service = new WhatsAppService($this->gatewayConfig);

            $result = $service->sendMessage(
                $this->recipient,
                $this->messageOrTemplate,
                $this->components,
                $this->type
            );

            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'فشل غير معروف في إرسال واتساب.');
            }

            // تسجيل الإرسال بنجاح
            NotificationLog::create([
                'type' => 'whatsapp',
                'recipient' => $this->recipient,
                'title' => $this->type === 'template' ? $this->messageOrTemplate : 'رسالة نصية',
                'content' => $this->type === 'template' ? json_encode($this->components) : $this->messageOrTemplate,
                'status' => 'sent',
                'company_id' => $this->companyId,
                'branch_id' => $this->branchId,
                'created_by' => $this->createdBy,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp in SendWhatsAppJob to {$this->recipient}", ['error' => $e->getMessage()]);

            // تسجيل الفشل في السجل
            NotificationLog::create([
                'type' => 'whatsapp',
                'recipient' => $this->recipient,
                'title' => $this->type === 'template' ? $this->messageOrTemplate : 'رسالة نصية',
                'content' => $this->type === 'template' ? json_encode($this->components) : $this->messageOrTemplate,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'company_id' => $this->companyId,
                'branch_id' => $this->branchId,
                'created_by' => $this->createdBy,
            ]);

            throw $e;
        }
    }
}
