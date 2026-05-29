<?php

namespace Modules\Payment\Actions;

// تعليق عربي: أكشن لمعالجة الـ Webhooks الواردة من بوابات الدفع الخارجية وتحديث حالة المعاملة.

use Modules\Core\Actions\BaseAction;
use Modules\Payment\Models\PaymentTransaction;
use Modules\Payment\Services\PaymentGatewayFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleWebhookAction extends BaseAction
{
    public function handle(array $data = []): array
    {
        $driverName = $data['driver'];
        $payload = $data['payload'];

        Log::info("Received payment webhook for driver: {$driverName}", ['payload' => $payload]);

        // 1. تحديد المعاملة المالية بناءً على المعطيات الواردة من البوابة
        $gatewayReference = null;
        $transactionId = null;

        if ($driverName === 'stripe') {
            $gatewayReference = $payload['data']['object']['id'] ?? null;
            $transactionId = $payload['data']['object']['client_reference_id'] ?? null;
        } elseif ($driverName === 'paymob') {
            $gatewayReference = $payload['obj']['order']['id'] ?? $payload['obj']['id'] ?? null;
            $transactionId = explode('_', $payload['obj']['merchant_order_id'] ?? '')[0] ?? null;
        }

        // العثور على المعاملة
        $transaction = null;
        if ($transactionId) {
            $transaction = PaymentTransaction::withoutGlobalScopes()->find($transactionId);
        }
        if (!$transaction && $gatewayReference) {
            $transaction = PaymentTransaction::withoutGlobalScopes()
                ->where('gateway_reference', $gatewayReference)
                ->first();
        }

        if (!$transaction) {
            Log::warning("PaymentTransaction not found for webhook", [
                'driver' => $driverName,
                'gateway_reference' => $gatewayReference,
                'transaction_id' => $transactionId
            ]);
            return ['success' => false, 'error' => 'المعاملة غير موجودة في النظام.'];
        }

        // 2. التحقق من صحة وحالة الدفع عبر الموفر الفعلي
        $gateway = $transaction->gateway;
        if (!$gateway) {
            return ['success' => false, 'error' => 'بوابة الدفع غير مرتبطة بالمعاملة.'];
        }

        $driver = PaymentGatewayFactory::create($gateway);
        
        // التحقق الفعلي من الطلب
        $verificationData = $driverName === 'stripe' 
            ? ['session_id' => $gatewayReference] 
            : ['order' => $gatewayReference, 'success' => $payload['obj']['success'] ?? false];

        $verifyResult = $driver->verify($verificationData);

        if (!$verifyResult['success']) {
            return ['success' => false, 'error' => $verifyResult['error'] ?? 'فشل التحقق من صحة الدفع.'];
        }

        // 3. تحديث حالة المعاملة في قاعدة البيانات ضمن Transaction
        DB::transaction(function() use ($transaction, $verifyResult, $payload) {
            $status = $verifyResult['status']; // completed, failed, etc.
            
            $transaction->update([
                'status' => $status,
                'payload' => array_merge($transaction->payload ?? [], [
                    'webhook_received_at' => now()->toDateTimeString(),
                    'webhook_payload' => $payload,
                    'verification_payload' => $verifyResult['payload'] ?? null
                ])
            ]);

            // إذا اكتمل الدفع بنجاح، نقوم بإثارة حدث أو تحديث الفاتورة/الاشتراك المرتبط
            if ($status === 'completed') {
                $payable = $transaction->payable;
                if ($payable && method_exists($payable, 'markAsPaid')) {
                    // إذا كان الكائن المرتبط يدعم التحديث التلقائي كمدفوع
                    $payable->markAsPaid($transaction);
                }
                
                // هنا يمكن إثارة Event: event(new PaymentReceivedEvent($transaction));
            }
        });

        return ['success' => true, 'status' => $verifyResult['status']];
    }
}
