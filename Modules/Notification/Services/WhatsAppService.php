<?php

namespace Modules\Notification\Services;

// تعليق عربي: خدمة إرسال التنبيهات عبر WhatsApp Cloud API باستخدام HTTP Client.

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use Modules\Notification\Models\WhatsAppSetting;

class WhatsAppService
{
    protected array $config;

    public function __construct($config = [])
    {
        if ($config instanceof WhatsAppSetting) {
            $config = [
                'access_token' => $config->access_token,
                'phone_number_id' => $config->phone_number_id,
            ];
        }

        // إذا كانت الإعدادات فارغة، نحاول تحميل الإعداد الافتراضي للشركة النشطة للمستخدم الحالي
        if (empty($config['access_token']) && empty($config['phone_number_id'])) {
            $user = auth()->user();
            if ($user && $user->active_company_id) {
                $setting = WhatsAppSetting::where('company_id', $user->active_company_id)
                    ->where('is_active', true)
                    ->where('is_default', true)
                    ->first();
                if ($setting) {
                    $config = [
                        'access_token' => $setting->access_token,
                        'phone_number_id' => $setting->phone_number_id,
                    ];
                }
            }
        }

        // استخدام الإعدادات الافتراضية من config أو الممررة يدوياً (مخصصة للشركة)
        $this->config = array_merge([
            'access_token' => env('WHATSAPP_ACCESS_TOKEN', ''),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
            'version' => 'v19.0',
        ], (array) $config);
    }

    /**
     * إرسال رسالة نصية بسيطة أو قالب (Template)
     */
    public function sendMessage(string $to, string $messageOrTemplate, array $components = [], string $type = 'text'): array
    {
        $accessToken = $this->config['access_token'];
        $phoneNumberId = $this->config['phone_number_id'];
        $version = $this->config['version'];

        if (!$accessToken || !$phoneNumberId) {
            return ['success' => false, 'error' => 'إعدادات WhatsApp Cloud API غير مكتملة.'];
        }

        try {
            $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";
            
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
            ];

            if ($type === 'template') {
                $payload['type'] = 'template';
                $payload['template'] = [
                    'name' => $messageOrTemplate, // اسم القالب المعتمد من ميتا
                    'language' => ['code' => 'ar'],
                    'components' => $components, // مصفوفة المتغيرات
                ];
            } else {
                $payload['type'] = 'text';
                $payload['text'] = [
                    'body' => $messageOrTemplate
                ];
            }

            $response = Http::withToken($accessToken)
                ->post($url, $payload);

            if ($response->failed()) {
                Log::error('WhatsApp API failed response', ['body' => $response->body()]);
                return ['success' => false, 'error' => $response->json('error.message') ?? $response->body()];
            }

            return [
                'success' => true,
                'message_id' => $response->json('messages.0.id'),
                'payload' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp send exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
