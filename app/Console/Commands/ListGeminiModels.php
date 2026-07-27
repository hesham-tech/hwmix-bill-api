<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\AiPlatform\Models\AiProviderAccount;
use Modules\AiPlatform\Contracts\Security\SecretVaultInterface;

class ListGeminiModels extends Command
{
    protected $signature = 'app:list-gemini-models';
    protected $description = 'List all Google Gemini models available for the configured API key';

    public function handle(): void
    {
        $account = AiProviderAccount::find(1);
        if (!$account) {
            $this->error("Account #1 not found"); return;
        }

        $vault = app(SecretVaultInterface::class);
        $plainKey = $vault->decrypt($account->api_key_encrypted);
        $this->info("Key prefix: " . substr($plainKey, 0, 8) . "...");

        $response = Http::timeout(15)->get(
            "https://generativelanguage.googleapis.com/v1beta/models?key={$plainKey}"
        );

        $this->info("HTTP Status: " . $response->status());

        if (!$response->successful()) {
            $this->error("Error: " . $response->body()); return;
        }

        $all = $response->json()['models'] ?? [];
        $supported = collect($all)
            ->filter(fn($m) => in_array('generateContent', $m['supportedGenerationMethods'] ?? []))
            ->values();

        $this->info("\nModels supporting generateContent (" . count($supported) . " total):");
        foreach ($supported as $m) {
            $this->line("  ✅ " . str_replace('models/', '', $m['name']));
        }
    }
}
