<?php
namespace App\Support;
final class SensitiveData {
    public static function redact(array $data): array {
        $result=[];
        foreach($data as $key=>$value) {
            $name=strtolower((string)$key);
            $sensitive=preg_match('/password|secret|token|api.?key|private.?key|authorization|card|signature|hmac|credentials|config|phone|email|address|street|recipient|account_number/',$name)
                ||preg_match('/(^|_)(pan|iban|cvv|cvc|otp|code)($|_)/',$name);
            $result[$key]=$sensitive?'[REDACTED]':(is_array($value)?self::redact($value):$value);
        }
        return $result;
    }
}
