<?php

namespace Modules\Payment\Actions;

//   أكشن لإنشاء معاملة دفع جديدة واستدعاء بوابة الدفع الإلكتروني لتجهيز رابط الدفع للمستخدم.

use Modules\Core\Actions\BaseAction;
use Modules\Payment\Models\PaymentGateway;
use Modules\Payment\Models\PaymentTransaction;
use Modules\Payment\Services\PaymentGatewayFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProcessPaymentAction extends BaseAction
{
    public function handle(array $data = []): array
    {
        $gatewayId = $data['payment_gateway_id'];
        $payableType = $data['payable_type'];
        $payableId = $data['payable_id'];
        $amount = $data['amount'];
        $currency = $data['currency'] ?? 'USD';
        $branchId = $data['branch_id'] ?? null;
        $options = $data['options'] ?? [];

        // العثور على بوابة الدفع
        $gateway = PaymentGateway::findOrFail($gatewayId);

        if (!$gateway->is_active) {
            throw new \Exception('بوابة الدفع المحددة غير نشطة حالياً.');
        }

        return DB::transaction(function () use ($gateway, $payableType, $payableId, $amount, $currency, $branchId, $options) {
            // إنشاء المعاملة المالية في قاعدة البيانات
            $transaction = PaymentTransaction::create([
                'payment_gateway_id' => $gateway->id,
                'payable_type' => $payableType,
                'payable_id' => $payableId,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'status' => 'pending',
                'company_id' => Auth::user()->active_company_id ?? $gateway->company_id,
                'branch_id' => $branchId ?? Auth::user()->branch_id ?? null,
                'created_by' => Auth::id(),
            ]);

            // إنشاء كائن المشغل الفعلي (Stripe/Paymob)
            $driver = PaymentGatewayFactory::create($gateway);

            // معالجة الدفع عبر البوابة الخارجية
            $result = $driver->purchase($transaction, $options);

            if (!$result['success']) {
                throw new \Exception('فشل الاتصال ببوابة الدفع: ' . ($result['error'] ?? 'خطأ غير معروف'));
            }

            // تحديث المعاملة بالرقم المرجعي للبوابة الخارجية
            $transaction->update([
                'gateway_reference' => $result['gateway_reference'] ?? null,
            ]);

            return [
                'transaction_id' => $transaction->id,
                'payment_url' => $result['payment_url'],
                'gateway_reference' => $result['gateway_reference'] ?? null,
            ];
        });
    }
}
