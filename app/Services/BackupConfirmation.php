<?php
namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

final class BackupConfirmation
{
    public const TTL = 300;
    public const HEADER = 'X-Backup-Confirmation-Token';

    private function binding(Request $request): string
    {
        $session = $request->hasSession() ? $request->session()->getId() : '';
        return hash('sha256', $request->bearerToken() ?? $session);
    }

    public function issue(Request $request, string $action, int $backupId): string
    {
        return Crypt::encryptString(json_encode([
            'user' => (string) $request->user()->getAuthIdentifier(),
            'binding' => $this->binding($request), 'action' => $action, 'backup_id' => $backupId,
            'expires' => now()->getTimestamp() + self::TTL,
        ], JSON_THROW_ON_ERROR));
    }

    public function valid(Request $request, string $action, int $backupId): bool
    {
        $token = $request->header(self::HEADER);
        if (!is_string($token) || $token === '' || strlen($token) > 4096) return false;
        try {
            $data = json_decode(Crypt::decryptString($token), true, 16, JSON_THROW_ON_ERROR);
            return is_array($data) && ($data['user'] ?? null) === (string) $request->user()->getAuthIdentifier()
                && is_string($data['binding'] ?? null) && hash_equals($this->binding($request), $data['binding'])
                && ($data['action'] ?? null) === $action && ($data['backup_id'] ?? null) === $backupId
                && is_int($data['expires'] ?? null) && $data['expires'] > now()->getTimestamp();
        } catch (\Throwable) {
            return false;
        }
    }
}
