<?php
namespace Modules\Notification\Services;
use Modules\Notification\Models\MailSetting;
use Illuminate\Support\Facades\Mail;

class DynamicMailer
{
    public static function getMailer(MailSetting $setting) {
        $name='tenant_mailer_'.$setting->id;
        config(['mail.mailers.'.$name=>[
            'transport'=>$setting->mail_transport?:'smtp','host'=>$setting->mail_host,'port'=>$setting->mail_port?:587,
            'encryption'=>$setting->mail_encryption,'username'=>$setting->mail_username,
            'password'=>$setting->mail_password,'timeout'=>10,
        ]]);
        // Rebuild after a credential/settings change, including within a persistent worker.
        if(method_exists(Mail::getFacadeRoot(),'purge')) Mail::purge($name);
        $mailer=Mail::mailer($name);
        if(method_exists($mailer,'alwaysFrom')) $mailer->alwaysFrom(
            $setting->mail_from_address?:config('mail.from.address'),
            $setting->mail_from_name?:config('mail.from.name')
        );
        if($setting->mail_reply_to_address) $mailer->alwaysReplyTo(
            $setting->mail_reply_to_address,$setting->mail_reply_to_name
        );
        return $mailer;
    }
}
