<?php

namespace Modules\Notification\Jobs;

// تعليق عربي: وظيفة خلفية (Job) لإرسال رسائل البريد الإلكتروني بشكل غير متزامن باستخدام الإعدادات الخاصة بالشركة.

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Notification\Models\MailSetting;
use Modules\Notification\Models\NotificationLog;
use Modules\Notification\Services\DynamicMailer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $companyId;
    protected ?int $branchId;
    protected string $recipient;
    protected string $subject;
    protected string $body;
    protected ?int $createdBy;

    public function __construct(int $companyId, ?int $branchId, string $recipient, string $subject, string $body, ?int $createdBy = null)
    {
        $this->companyId = $companyId;
        $this->branchId = $branchId;
        $this->recipient = $recipient;
        $this->subject = $subject;
        $this->body = $body;
        $this->createdBy = $createdBy;
    }

    public function handle(): void
    {
        // 1. البحث عن إعدادات البريد الافتراضية والنشطة الخاصة بالشركة (غير العامة)
        $setting = MailSetting::withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->where('is_global', false)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        // 2. في حال لم يجد، نأخذ أي حساب نشط للشركة نفسها (غير العامة) كـ fallback
        if (!$setting) {
            $setting = MailSetting::withoutGlobalScopes()
                ->where('company_id', $this->companyId)
                ->where('is_global', false)
                ->where('is_active', true)
                ->first();
        }

        // 3. في حال لم يجد، نأخذ الحساب الافتراضي العام للنظام (سيستم)
        if (!$setting) {
            $setting = MailSetting::withoutGlobalScopes()
                ->where('is_global', true)
                ->where('is_active', true)
                ->where('is_default', true)
                ->first();
        }

        // 4. في حال لم يجد، نأخذ أي حساب عام نشط للنظام
        if (!$setting) {
            $setting = MailSetting::withoutGlobalScopes()
                ->where('is_global', true)
                ->where('is_active', true)
                ->first();
        }

        try {
            if ($setting) {
                // استخدام الموزع الديناميكي المخصص للشركة أو النظام
                $mailer = DynamicMailer::getMailer($setting);
                
                $mailer->html($this->body, function ($message) {
                    $message->to($this->recipient)->subject($this->subject);
                });
            } else {
                throw new \Exception('تكامل البريد الإلكتروني غير متاح أو غير مهيأ للنظام أو الشركة.');
            }

            // تسجيل العملية بنجاح في سجلات التنبيهات
            NotificationLog::create([
                'type' => 'email',
                'recipient' => $this->recipient,
                'title' => $this->subject,
                'content' => $this->body,
                'status' => 'sent',
                'company_id' => $this->companyId,
                'branch_id' => $this->branchId,
                'created_by' => $this->createdBy,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send mail in SendMailJob to {$this->recipient}", ['error' => $e->getMessage()]);

            // تسجيل الفشل
            NotificationLog::create([
                'type' => 'email',
                'recipient' => $this->recipient,
                'title' => $this->subject,
                'content' => $this->body,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'company_id' => $this->companyId,
                'branch_id' => $this->branchId,
                'created_by' => $this->createdBy,
            ]);

            throw $e; // لإعادة المحاولة من طابور Horizon
        }
    }
}
