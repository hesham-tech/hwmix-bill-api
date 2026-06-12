<?php

namespace Modules\Payment\Services\Gateways;

//   خدمة معالجة الدفع عبر بوابة Paymob الإلكترونية باستخدام HTTP Client المدمج.

use Modules\Payment\Contracts\PaymentGatewayInterface;
use Modules\Payment\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymobGateway implements PaymentGatewayInterface
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function purchase(PaymentTransaction $transaction, array $options = []): array
    {
        $apiKey = $this->config['api_key'] ?? '';
        $integrationId = $this->config['integration_id'] ?? '';
        $iframeId = $this->config['iframe_id'] ?? '';

        try {
            // الخطوة 1: الحصول على Auth Token
            $authResponse = Http::post('https://accept.paymob.com/api/auth/tokens', [
                'api_key' => $apiKey,
            ]);

            if ($authResponse->failed()) {
                throw new \Exception('فشل الاتصال الأولي ببوابة Paymob: ' . $authResponse->body());
            }

            $authToken = $authResponse->json('token');

            // الخطوة 2: تسجيل الطلب (Order Registration)
            $orderResponse = Http::post('https://accept.paymob.com/api/ecommerce/orders', [
                'auth_token' => $authToken,
                'delivery_needed' => 'false',
                'amount_cents' => (int) ($transaction->amount * 100),
                'currency' => $transaction->currency,
                'merchant_order_id' => $transaction->id . '_' . time(),
            ]);

            if ($orderResponse->failed()) {
                throw new \Exception('فشل تسجيل الطلب في Paymob: ' . $orderResponse->body());
            }

            $orderId = $orderResponse->json('id');

            // الخطوة 3: توليد مفتاح الدفع (Payment Key)
            $billingData = array_merge([
                'first_name' => 'Cl',
                'last_name' => 'Name',
                'email' => 'client@hwnix.com',
                'phone_number' => '01000000000',
                'apartment' => 'NA',
                'floor' => 'NA',
                'street' => 'NA',
                'building' => 'NA',
                'shipping_method' => 'NA',
                'postal_code' => 'NA',
                'city' => 'NA',
                'country' => 'EG',
                'state' => 'NA'
            ], $options['billing_data'] ?? []);

            $keyResponse = Http::post('https://accept.paymob.com/api/acceptance/payment_keys', [
                'auth_token' => $authToken,
                'amount_cents' => (int) ($transaction->amount * 100),
                'expiration' => 3600,
                'order_id' => $orderId,
                'billing_data' => $billingData,
                'currency' => $transaction->currency,
                'integration_id' => $integrationId,
            ]);

            if ($keyResponse->failed()) {
                throw new \Exception('فشل توليد مفتاح الدفع من Paymob: ' . $keyResponse->body());
            }

            $paymentToken = $keyResponse->json('token');
            $paymentUrl = "https://accept.paymob.com/api/acceptance/iframes/{$iframeId}?payment_token={$paymentToken}";

            return [
                'success' => true,
                'payment_url' => $paymentUrl,
                'gateway_reference' => $orderId,
            ];
        } catch (\Exception $e) {
            Log::error('Paymob purchase error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function verify(array $data): array
    {
        // التحقق من صحة معاملة Paymob (من الـ HMAC أو عبر رقم طلب البوابة)
        $orderId = $data['order'] ?? $data['id'] ?? '';
        $apiKey = $this->config['api_key'] ?? '';

        if (!$orderId) {
            return ['success' => false, 'error' => 'رقم الطلب غير متوفر.'];
        }

        try {
            // الحصول على Auth Token للاستعلام
            $authResponse = Http::post('https://accept.paymob.com/api/auth/tokens', [
                'api_key' => $apiKey,
            ]);

            if ($authResponse->failed()) {
                return ['success' => false, 'error' => 'فشل التوثيق مع Paymob.'];
            }

            $authToken = $authResponse->json('token');

            // الاستعلام عن حالة الطلب
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $authToken
            ])->get("https://accept.paymob.com/api/ecommerce/orders/{$orderId}");

            if ($response->failed()) {
                return ['success' => false, 'error' => 'فشل التحقق من الطلب لدى Paymob.'];
            }

            $order = $response->json();

            // التحقق من حالة المعاملة
            $success = $data['success'] ?? ($order['is_paid'] ?? false);
            if ($success === true || $success === 'true' || ($order['is_paid'] ?? false) === true) {
                return [
                    'success' => true,
                    'status' => 'completed',
                    'gateway_reference' => $orderId,
                    'payload' => $order,
                ];
            }

            return [
                'success' => true,
                'status' => 'failed',
                'gateway_reference' => $orderId,
                'payload' => $order,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function refund(PaymentTransaction $transaction, float $amount): array
    {
        $apiKey = $this->config['api_key'] ?? '';

        try {
            $authResponse = Http::post('https://accept.paymob.com/api/auth/tokens', [
                'api_key' => $apiKey,
            ]);

            if ($authResponse->failed()) {
                return ['success' => false, 'error' => 'فشل التوثيق مع Paymob.'];
            }

            $authToken = $authResponse->json('token');

            $response = Http::post('https://accept.paymob.com/api/acceptance/void_refund/refund', [
                'auth_token' => $authToken,
                'transaction_id' => $transaction->gateway_reference,
                'amount_cents' => (int) ($amount * 100),
            ]);

            if ($response->failed()) {
                return ['success' => false, 'error' => $response->json('detail') ?? $response->body()];
            }

            return [
                'success' => true,
                'status' => 'refunded',
                'gateway_reference' => $response->json('id'),
                'payload' => $response->json(),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
