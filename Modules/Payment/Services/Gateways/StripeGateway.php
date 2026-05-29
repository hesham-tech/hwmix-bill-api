<?php

namespace Modules\Payment\Services\Gateways;

// تعليق عربي: خدمة معالجة الدفع عبر Stripe باستخدام HTTP Client التابع لـ Laravel.

use Modules\Payment\Contracts\PaymentGatewayInterface;
use Modules\Payment\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeGateway implements PaymentGatewayInterface
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function purchase(PaymentTransaction $transaction, array $options = []): array
    {
        $apiKey = $this->config['secret_key'] ?? '';
        
        try {
            // إنشاء Checkout Session على Stripe
            $response = Http::withToken($apiKey)
                ->asForm()
                ->post('https://api.stripe.com/v1/checkout/sessions', [
                    'payment_method_types' => ['card'],
                    'line_items' => [
                        [
                            'price_data' => [
                                'currency' => strtolower($transaction->currency),
                                'product_data' => [
                                    'name' => "دفع معاملة #{$transaction->id}",
                                ],
                                'unit_amount' => (int) ($transaction->amount * 100), // القروش
                            ],
                            'quantity' => 1,
                        ]
                    ],
                    'mode' => 'payment',
                    'success_url' => $options['success_url'] ?? route('payment.callback', ['driver' => 'stripe', 'status' => 'success']),
                    'cancel_url' => $options['cancel_url'] ?? route('payment.callback', ['driver' => 'stripe', 'status' => 'cancel']),
                    'client_reference_id' => $transaction->id,
                ]);

            if ($response->failed()) {
                Log::error('Stripe Checkout Session creation failed', ['response' => $response->body()]);
                throw new \Exception('فشل إنشاء جلسة الدفع في Stripe: ' . ($response->json('error.message') ?? $response->body()));
            }

            return [
                'success' => true,
                'payment_url' => $response->json('url'),
                'gateway_reference' => $response->json('id'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function verify(array $data): array
    {
        // التحقق من صحة طلب الويب-هوك أو الكولباك لـ Stripe
        $apiKey = $this->config['secret_key'] ?? '';
        $sessionId = $data['session_id'] ?? '';

        if (!$sessionId) {
            return ['success' => false, 'error' => 'كود الجلسة غير متوفر.'];
        }

        try {
            $response = Http::withToken($apiKey)
                ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}");

            if ($response->failed()) {
                return ['success' => false, 'error' => 'فشل التحقق من الجلسة في Stripe.'];
            }

            $session = $response->json();
            if ($session['payment_status'] === 'paid') {
                return [
                    'success' => true,
                    'status' => 'completed',
                    'gateway_reference' => $session['id'],
                    'payload' => $session,
                ];
            }

            return [
                'success' => true,
                'status' => 'failed',
                'gateway_reference' => $session['id'],
                'payload' => $session,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function refund(PaymentTransaction $transaction, float $amount): array
    {
        $apiKey = $this->config['secret_key'] ?? '';
        
        try {
            $response = Http::withToken($apiKey)
                ->asForm()
                ->post('https://api.stripe.com/v1/refunds', [
                    'payment_intent' => $transaction->gateway_reference,
                    'amount' => (int) ($amount * 100),
                ]);

            if ($response->failed()) {
                return ['success' => false, 'error' => $response->json('error.message') ?? 'فشل الاسترداد.'];
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
