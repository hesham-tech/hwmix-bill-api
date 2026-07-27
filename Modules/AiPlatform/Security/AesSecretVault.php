<?php

// خزينة التشفير AES-256 لمفاتيح API
namespace Modules\AiPlatform\Security;

use Modules\AiPlatform\Contracts\Security\SecretVaultInterface;
use RuntimeException;

class AesSecretVault implements SecretVaultInterface
{
    private string $encryptionKey;

    public function __construct()
    {
        $key = config('ai-platform.secret_key', config('app.key'));

        if (empty($key)) {
            throw new RuntimeException('AI Platform: مفتاح التشفير AI_SECRET_KEY غير مُعيَّن في .env');
        }

        // تأكد أن المفتاح مناسب لـ AES-256
        $this->encryptionKey = substr(hash('sha256', $key, true), 0, 32);
    }

    public function encrypt(string $apiKey): string
    {
        $iv        = random_bytes(16);
        $encrypted = openssl_encrypt($apiKey, 'AES-256-CBC', $this->encryptionKey, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new RuntimeException('AI Platform: فشل تشفير المفتاح');
        }

        return base64_encode($iv . $encrypted);
    }

    public function decrypt(string $encryptedKey): string
    {
        // إذا كان المفتاح يبدأ بـ AIza (Google Gemini) أو sk- (OpenAI) فهو نص خام غير مشفر مسبقاً
        if (str_starts_with($encryptedKey, 'AIza') || str_starts_with($encryptedKey, 'sk-')) {
            return $encryptedKey;
        }

        $data = base64_decode($encryptedKey, true);

        if ($data === false || strlen($data) < 17) {
            return $encryptedKey;
        }

        $iv        = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $decrypted = @openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            return $encryptedKey;
        }

        return $decrypted;
    }

    public function rotate(string $encryptedKey, string $newEncryptionKey): string
    {
        // فك التشفير بالمفتاح القديم
        $plainKey = $this->decrypt($encryptedKey);

        // تشفير بالمفتاح الجديد
        $newKey       = substr(hash('sha256', $newEncryptionKey, true), 0, 32);
        $iv           = random_bytes(16);
        $encrypted    = openssl_encrypt($plainKey, 'AES-256-CBC', $newKey, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . $encrypted);
    }

    public function hint(string $apiKey): string
    {
        $clean = str_replace(['sk-', 'AIza', 'Bearer '], '', $apiKey);
        return '...' . substr($clean, -4);
    }
}
