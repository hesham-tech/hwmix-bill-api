<?php
namespace Modules\Payment\Services;
use Modules\Payment\Contracts\PaymentGatewayInterface;
use Modules\Payment\Models\PaymentGateway;
use Modules\Payment\Models\PaymentTransaction;
use Illuminate\Support\Facades\Crypt;
class PaymentGatewayFactory {
    public static function configuration(PaymentGateway $gateway): array {
        $config=$gateway->config??[];
        if(is_string($config)) $config=json_decode(Crypt::decryptString($config),true)?:[];
        foreach($config as $key=>$value) {
            if(is_string($value)) {
                try { $config[$key]=Crypt::decryptString($value); }
                catch(\Illuminate\Contracts\Encryption\DecryptException) { /* Legacy plaintext configuration. */ }
            }
        }
        $config['_test_mode']=(bool)$gateway->is_test_mode;
        return $config;
    }
    public static function create(PaymentGateway $gateway): PaymentGatewayInterface {
        return GatewayRegistry::make($gateway->driver,self::configuration($gateway));
    }
    public static function forTransaction(PaymentTransaction $transaction): PaymentGatewayInterface {
        if(!$transaction->gateway_config_snapshot) {
            if(!$transaction->gateway) throw new \RuntimeException('إعدادات حساب العملية غير متاحة.');
            return self::create($transaction->gateway);
        }
        return GatewayRegistry::make($transaction->gateway_driver,$transaction->gateway_config_snapshot);
    }
}
