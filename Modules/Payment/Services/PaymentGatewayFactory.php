<?php

namespace Modules\Payment\Services;

// تعليق عربي: مصنع بوابات الدفع لإنشاء كائن معالج البوابة المحدد بناءً على إعدادات الشركة المشفرة.

use Modules\Payment\Contracts\PaymentGatewayInterface;
use Modules\Payment\Models\PaymentGateway;
use Modules\Payment\Services\Gateways\StripeGateway;
use Modules\Payment\Services\Gateways\PaymobGateway;
use Illuminate\Support\Facades\Crypt;

class PaymentGatewayFactory
{
    /**
     * إنشاء كائن بوابة الدفع المطابق
     */
    public static function create(PaymentGateway $gateway): PaymentGatewayInterface
    {
        // فك تشفير الإعدادات قبل التمرير
        $config = $gateway->config;
        if (is_string($config)) {
            try {
                $config = json_decode(Crypt::decryptString($config), true);
            } catch (\Exception $e) {
                $config = [];
            }
        } elseif (is_array($config)) {
            // فك التشفير لكل قيمة نصية مشفرة في الإعدادات
            $config = array_map(function($val) {
                if (is_string($val)) {
                    try {
                        return Crypt::decryptString($val);
                    } catch (\Exception $e) {
                        return $val;
                    }
                }
                return $val;
            }, $config);
        }

        switch (strtolower($gateway->driver)) {
            case 'stripe':
                return new StripeGateway($config);
            case 'paymob':
                return new PaymobGateway($config);
            default:
                throw new \InvalidArgumentException("مشغل الدفع '{$gateway->driver}' غير مدعوم.");
        }
    }
}
