<?php

namespace Modules\Payment\Contracts;

//   واجهة بوابة الدفع (Strategy Pattern) لتوحيد المعالجة لكل البوابات (Stripe, Paymob).

use Modules\Payment\Models\PaymentTransaction;

interface PaymentGatewayInterface
{
    /**
     * معالجة الدفع وإنشاء الجلسة/الفاتورة على البوابة الخارجية
     */
    public function purchase(PaymentTransaction $transaction, array $options = []): array;

    /**
     * التحقق من المعاملة عند استلام الاستجابة (Webhook/Callback)
     */
    public function verify(array $data): array;

    /**
     * استرداد مبلغ المعاملة
     */
    public function refund(PaymentTransaction $transaction, float $amount): array;
}
