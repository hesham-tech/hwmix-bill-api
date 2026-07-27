<?php

// عقد إدارة وتشفير مفاتيح API
namespace Modules\AiPlatform\Contracts\Security;

interface SecretVaultInterface
{
    /** تشفير API Key */
    public function encrypt(string $apiKey): string;

    /** فك تشفير API Key */
    public function decrypt(string $encryptedKey): string;

    /** تدوير المفتاح */
    public function rotate(string $encryptedKey, string $newEncryptionKey): string;

    /** استخراج hint (آخر 4 أحرف للعرض) */
    public function hint(string $apiKey): string;
}
