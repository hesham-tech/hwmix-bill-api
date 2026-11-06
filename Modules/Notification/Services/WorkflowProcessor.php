<?php

namespace Modules\Notification\Services;

//   معالج أتمتة الإشعارات للتعامل مع تبديل المتغيرات الديناميكية وإرسال الرسائل عبر البريد والواتساب بشكل معزول لكل شركة.

use Modules\Notification\Models\NotificationWorkflow;
use Modules\Notification\Models\NotificationWorkflowStep;
use Modules\Notification\Services\WhatsAppService;
use Modules\Notification\Services\DynamicMailer;
use Modules\Notification\Models\MailSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\InvoicePayment;

class WorkflowProcessor
{
    public function deliverScheduledEmail(NotificationWorkflowStep $step,Invoice $invoice,string $key): string {
        $template=$step->template;$companyId=(int)$step->workflow->company_id;
        if(!$template||!$template->is_active)return 'completed';
        if((int)$template->company_id!==$companyId&&!($template->is_global&&($template->company_id===null||(int)$template->company_id===(int)config('app.master_company_id',1))))
            throw new \RuntimeException('Notification template ownership mismatch.');
        $recipient=$invoice->customer;
        if(!$recipient||!filter_var($recipient->email,FILTER_VALIDATE_EMAIL))return 'manual_review';
        $this->sendTransactionalEmail($companyId,$recipient->email,$this->replaceVariables($template->subject?:'إشعار فاتورة',$invoice,$recipient),
            $this->replaceVariables($template->body,$invoice,$recipient,true),$key,$recipient->name??'');
        return 'completed';
    }
    public function sendTransactionalEmail(int $companyId,string $toEmail,string $subject,string $body,?string $messageKey=null,string $toName=''): void {
        $setting=app(NotificationIntegrationResolver::class)->mail($companyId);
        if(app()->environment('testing'))$mailer=Mail::mailer('array');
        elseif($setting)$mailer=DynamicMailer::getMailer($setting);
        else throw new \RuntimeException('Transactional mail is not configured.');
        $subject=str_replace(["\r","\n"],' ',$subject);
        $mailer->html($body,function($message)use($toEmail,$toName,$subject,$messageKey,$setting){
            $message->to($toEmail,$toName)->subject($subject);
            if($messageKey){
                $domain=substr(strrchr((string)($setting?->mail_from_address??config('mail.from.address','store@store.invalid')),'@')?:'@store.invalid',1);
                $headers=$message->getSymfonyMessage()->getHeaders();$headers->remove('Message-ID');$headers->addIdHeader('Message-ID',hash('sha256',$messageKey).'@'.$domain);
            }
        });
    }
    /**
     * تنفيذ خطوات الأتمتة لقاعدة معينة.
     */
    public function executeWorkflow(NotificationWorkflow $workflow, $entity, ?int $delayDays = null)
    {
        $steps = $workflow->steps()
            ->where('is_active', true)
            ->when($delayDays !== null, function ($q) use ($delayDays) {
                return $q->where('delay_days', $delayDays);
            })
            ->get();

        foreach ($steps as $step) {
            $this->executeStep($step, $entity);
        }
    }

    /**
     * تنفيذ خطوة أتمتة واحدة.
     */
    public function executeStep(NotificationWorkflowStep $step, $entity)
    {
        $template = $step->template;
        if (!$template || !$template->is_active)
            return;
        if((int)$template->company_id!==(int)$step->workflow->company_id&&!($template->is_global&&($template->company_id===null||(int)$template->company_id===(int)config('app.master_company_id',1))))
            throw new \RuntimeException('Notification template does not belong to workflow company.');

        $companyId = $step->workflow->company_id;

        // الحصول على العميل المستهدف والبيانات الديناميكية للتعويض
        $recipient = $this->resolveRecipient($entity);
        if (!$recipient) {
            Log::warning("تنبيه أتمتة: لم يتم العثور على العميل المستهدف للمستند.");
            return;
        }

        // التحقق من توافر قنوات الاتصال والربط النشطة للشركة أو العامة بالنظام
        $resolver=app(NotificationIntegrationResolver::class);
        $hasEmail=(bool)$resolver->mail((int)$companyId);
        $hasWhatsApp=(bool)$resolver->whatsapp((int)$companyId);

        $replacedContent = $this->replaceVariables($template->body, $entity, $recipient, true);
        $subject = $template->subject ? $this->replaceVariables($template->subject, $entity, $recipient) : 'إشعار جديد';

        // channel مخزن كـ JSON array مثل ['email', 'whatsapp'] — نحوله لمصفوفة PHP
        $rawChannel = $step->channel;
        if (is_string($rawChannel)) {
            // دعم القيم القديمة: 'both', 'email', 'whatsapp'
            $channels = match ($rawChannel) {
                'both' => ['email', 'whatsapp'],
                default => [$rawChannel],
            };
        } else {
            $channels = (array) $rawChannel;
        }

        if (in_array('email', $channels)) {
            if ($hasEmail && !empty($recipient->email)) {
                $this->sendEmail($companyId, $recipient->email, (string)($recipient->name??$recipient->full_name??''), $subject, $replacedContent);
            } else {
                Log::warning("تنبيه أتمتة: تم تخطي إرسال البريد للشركة {$companyId} لعدم تهيئة الخدمة أو نقص بيانات المستلم.");
            }
        }

        if (in_array('whatsapp', $channels)) {
            if ($hasWhatsApp && !empty($recipient->phone)) {
                $this->sendWhatsApp($companyId, $recipient->phone, strip_tags($this->replaceVariables($template->body,$entity,$recipient)));
            } else {
                Log::warning("تنبيه أتمتة: تم تخطي إرسال الواتساب للشركة {$companyId} لعدم تهيئة الخدمة أو نقص بيانات المستلم.");
            }
        }
    }

    /**
     * جلب العميل المرتبط بالكيان.
     */
    protected function resolveRecipient($entity)
    {
        if ($entity instanceof User) {
            return $entity;
        }
        if ($entity instanceof Invoice) {
            return $entity->customer;
        }
        if ($entity instanceof InvoicePayment) {
            return $entity->invoice?->customer;
        }
        if ($entity instanceof \App\Models\Transaction) {
            return $entity->customer;
        }
        if ($entity instanceof \App\Models\Task) {
            $assignment = $entity->assignments()->where('assignable_type', User::class)->first();
            if ($assignment) {
                return $assignment->assignable;
            }
            return $entity->creator;
        }
        if ($entity instanceof \Modules\Inventory\Models\Product) {
            return $entity->creator;
        }
        if ($entity instanceof \Modules\Inventory\Models\Stock) {
            return $entity->creator ?? $entity->variant?->product?->creator;
        }
        if ($entity instanceof \App\Models\CashBox || $entity instanceof \Modules\Accounting\Models\CashBox) {
            return $entity->creator;
        }
        if ($entity instanceof \App\Models\Installment) {
            return $entity->user;
        }
        if ($entity instanceof \App\Models\InstallmentPlan) {
            return $entity->customer;
        }
        if ($entity instanceof \App\Models\InstallmentPayment) {
            return $entity->plan?->customer;
        }
        if (isset($entity->customer) && $entity->customer instanceof User) {
            return $entity->customer;
        }
        if (isset($entity->user) && $entity->user instanceof User) {
            return $entity->user;
        }
        return null;
    }

    /**
     * استبدال المتغيرات الديناميكية في النص.
     */
    protected function replaceVariables(string $text, $entity, User $recipient, bool $escape = false): string
    {
        $variables = [
            '{customer_name}' => $recipient->name ?? '',
            '{customer_phone}' => $recipient->phone ?? '',
            '{customer_email}' => $recipient->email ?? '',
        ];

        if ($entity instanceof Invoice) {
            $variables['{invoice_number}'] = $entity->invoice_number;
            $variables['{invoice_amount}'] = number_format((float) $entity->net_amount, 2);
            $variables['{remaining_amount}'] = number_format((float) $entity->remaining_amount, 2);
            $variables['{due_date}'] = $entity->due_date ? $entity->due_date->toDateString() : '';
            $variables['{invoice_date}'] = $entity->created_at ? $entity->created_at->toDateString() : '';
        }

        if ($entity instanceof InvoicePayment) {
            $variables['{payment_amount}'] = number_format((float) $entity->amount, 2);
            $variables['{invoice_number}'] = $entity->invoice?->invoice_number ?? '';
            $variables['{remaining_amount}'] = number_format((float) ($entity->invoice?->remaining_amount ?? 0), 2);
            $variables['{payment_date}'] = $entity->created_at ? $entity->created_at->toDateString() : '';
        }

        if ($entity instanceof \App\Models\Transaction) {
            $variables['{transaction_id}'] = $entity->id;
            $variables['{transaction_type}'] = $entity->type;
            $variables['{transaction_amount}'] = number_format((float) $entity->amount, 2);
            $variables['{transaction_description}'] = $entity->description ?? '';
            $variables['{transaction_date}'] = $entity->created_at ? $entity->created_at->toDateString() : '';
        }

        if ($entity instanceof \App\Models\Task) {
            $variables['{task_title}'] = $entity->title;
            $variables['{task_status}'] = $entity->status;
            $variables['{task_priority}'] = $entity->priority;
            $variables['{task_progress}'] = $entity->progress . '%';
            $variables['{task_deadline}'] = $entity->deadline ? $entity->deadline->toDateString() : '';
            $variables['{task_description}'] = $entity->description ?? '';
        }

        if ($entity instanceof \Modules\Inventory\Models\Product) {
            $variables['{product_name}'] = $entity->name;
            $variables['{product_sku}'] = $entity->variants()->first()?->sku ?? '';
            $variables['{product_price}'] = number_format((float) ($entity->variants()->first()?->retail_price ?? 0), 2);
        }

        if ($entity instanceof \Modules\Inventory\Models\Stock) {
            $variables['{product_name}'] = $entity->variant?->product?->name ?? '';
            $variables['{product_sku}'] = $entity->variant?->sku ?? '';
            $variables['{stock_quantity}'] = $entity->quantity;
            $variables['{product_price}'] = number_format((float) ($entity->variant?->retail_price ?? 0), 2);
        }

        if ($entity instanceof \App\Models\CashBox || $entity instanceof \Modules\Accounting\Models\CashBox) {
            $variables['{cashbox_name}'] = $entity->name;
            $variables['{cashbox_balance}'] = number_format((float) $entity->balance, 2);
        }

        if ($entity instanceof \App\Models\Installment) {
            $variables['{installment_plan_name}'] = $entity->installmentPlan?->name ?? '';
            $variables['{installment_amount}'] = number_format((float) $entity->amount, 2);
            $variables['{installment_remaining_amount}'] = number_format((float) $entity->remaining, 2);
            $variables['{installment_number}'] = $entity->installment_number;
            $variables['{installment_due_date}'] = $entity->due_date ? $entity->due_date->toDateString() : '';
            $variables['{installment_paid_at}'] = $entity->paid_at ? $entity->paid_at->toDateString() : '';
            $variables['{installment_status}'] = $entity->status ?? '';
            $variables['{installment_invoice_number}'] = $entity->installmentPlan?->invoice?->invoice_number ?? '';
            $variables['{installment_plan_total}'] = number_format((float) ($entity->installmentPlan?->total_amount ?? 0), 2);
            $variables['{installment_plan_collected}'] = number_format((float) ($entity->installmentPlan?->total_collected ?? 0), 2);
            $variables['{installment_plan_progress}'] = ($entity->installmentPlan?->payment_progress ?? 0) . '%';
            $variables['{total_installments}'] = $entity->installmentPlan?->number_of_installments ?? 0;
            $variables['{paid_installments}'] = $entity->installmentPlan?->installments()->where('status', 'paid')->count() ?? 0;
            $variables['{remaining_installments}'] = $entity->installmentPlan?->installments()->where('status', '!=', 'paid')->count() ?? 0;
            $variables['{remaining_total_amount}'] = number_format((float) ($entity->installmentPlan?->remaining_amount ?? 0), 2);
        }

        if ($entity instanceof \App\Models\InstallmentPlan) {
            $variables['{installment_plan_name}'] = $entity->name;
            $variables['{total_installments}'] = $entity->number_of_installments;
            $variables['{remaining_total_amount}'] = number_format((float) $entity->remaining_amount, 2);
            $variables['{installment_amount}'] = number_format((float) $entity->installment_amount, 2);
            $variables['{installment_start_date}'] = $entity->start_date ? $entity->start_date->toDateString() : '';
        }

        if ($entity instanceof \App\Models\InstallmentPayment) {
            $variables['{installment_payment_amount}'] = number_format((float) $entity->amount_paid, 2);
            $variables['{installment_payment_date}'] = $entity->payment_date ? \Carbon\Carbon::parse($entity->payment_date)->toDateString() : '';
            $variables['{installment_payment_method}'] = $entity->payment_method ?? '';
            $variables['{installment_payment_reference}'] = $entity->reference_number ?? '';
            $variables['{installment_plan_name}'] = $entity->plan?->name ?? '';
            $variables['{installment_invoice_number}'] = $entity->plan?->invoice?->invoice_number ?? '';
            $variables['{installment_plan_total}'] = number_format((float) ($entity->plan?->total_amount ?? 0), 2);
            $variables['{installment_plan_collected}'] = number_format((float) ($entity->plan?->total_collected ?? 0), 2);
            $variables['{installment_plan_remaining}'] = number_format((float) ($entity->plan?->remaining_amount ?? 0), 2);
            $variables['{installment_plan_progress}'] = ($entity->plan?->payment_progress ?? 0) . '%';
        }

        if($escape)$variables=array_map(fn($value)=>e((string)$value),$variables);
        return strtr($text, $variables);
    }

    /**
     * إرسال البريد باستخدام إعدادات البريد الافتراضية للشركة أو النظام.
     */
    protected function sendEmail($companyId,string $toEmail,string $toName,string $subject,string $body) {
        try {$this->sendTransactionalEmail((int)$companyId,$toEmail,$subject,$body,null,$toName);}
        catch(\Throwable $error){Log::warning('Workflow email failed',['company_id'=>$companyId,'exception_class'=>get_class($error)]);}
    }
    protected function sendWhatsApp($companyId,string $toPhone,string $body) {
        $setting=app(NotificationIntegrationResolver::class)->whatsapp((int)$companyId);
        if(!$setting)return;
        $result=(new WhatsAppService($setting))->sendMessage(preg_replace('/[^0-9]/','',$toPhone),$body);
        if(!($result['success']??false))Log::warning('Workflow WhatsApp delivery was not confirmed',['company_id'=>$companyId]);
    }
}
