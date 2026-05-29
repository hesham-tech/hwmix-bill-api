<?php

namespace Modules\Notification\Services;

// تعليق عربي: خدمة إرسال التنبيهات عبر WhatsApp Cloud API باستخدام HTTP Client.

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        // استخدام الإعدادات الافتراضية من config أو الممررة يدوياً (مخصصة للشركة)
        $this->config = array_merge([
            'access_token' => env('WHATSAPP_ACCESS_TOKEN', ''),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
            'version' => 'v19.0',
        ], $config);
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
