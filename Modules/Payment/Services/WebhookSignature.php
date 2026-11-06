<?php

namespace Modules\Payment\Services;

final class WebhookSignature
{
    public static function kashier(array $data,string $signature,string $key): bool {
        if($key===''||!preg_match('/^[a-f0-9]{64}$/iD',$signature))return false;
        $keys=$data['signatureKeys']??[];$required=['amount','currency','kashierOrderId','merchantOrderId','transactionId','status'];
        if(!is_array($keys)||array_diff($required,$keys)||count($keys)!==count(array_unique($keys)))return false;
        sort($keys,SORT_STRING);$parts=[];
        foreach($keys as $field) {
            if(!is_string($field)||!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D',$field)||!is_scalar($data[$field]??null))return false;
            $parts[]=$field.'='.rawurlencode((string)$data[$field]);
        }
        return hash_equals(hash_hmac('sha256',implode('&',$parts),$key),strtolower($signature));
    }
    public static function stripe(string $body, string $header, string $secret, ?int $now = null): bool
    {
        if ($secret === '' || $body === '') return false;
        $values = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $values[$key][] = $value;
        }
        $timestamp = $values['t'][0] ?? '';
        if (!ctype_digit($timestamp) || abs(($now ?? time()) - (int) $timestamp) > 300) return false;
        $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        foreach ($values['v1'] ?? [] as $signature) {
            if (hash_equals($expected, $signature)) return true;
        }
        return false;
    }

    public static function paymob(array $object, string $signature, string $secret): bool
    {
        if ($secret === '' || !preg_match('/^[a-f0-9]{128}$/iD', $signature)) return false;
        $fields = ['amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction',
            'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded',
            'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending',
            'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success'];
        $message = '';
        foreach ($fields as $field) {
            $value = $object;
            foreach (explode('.', $field) as $key) {
                if (!is_array($value) || !array_key_exists($key, $value)) return false;
                $value = $value[$key];
            }
            if (!is_scalar($value)) return false;
            $message .= is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }
        return hash_equals(hash_hmac('sha512', $message, $secret), strtolower($signature));
    }
}
