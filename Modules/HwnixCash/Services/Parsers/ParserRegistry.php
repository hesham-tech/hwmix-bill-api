<?php
// سجل وقاعدة بيانات المحللات المركزية للرسائل مع الفهرسة المسبقة عند الإقلاع.

namespace Modules\HwnixCash\Services\Parsers;

use Modules\HwnixCash\Contracts\Parsers\ProviderParserInterface;
use Modules\HwnixCash\DTOs\NormalizedSmsContext;

final class ParserRegistry
{
    /** @var array<string, ProviderParserInterface> Key -> Parser */
    private array $providerKeyMap = [];

    /** @var array<string, ProviderParserInterface> Alias -> Parser */
    private array $aliasMap = [];

    /** @var array<array{pattern: string, parser: ProviderParserInterface}> */
    private array $regexPatterns = [];

    private bool $isWarmedUp = false;

    /**
     * @param iterable<ProviderParserInterface> $parsers
     */
    public function __construct(iterable $parsers = [])
    {
        foreach ($parsers as $parser) {
            $this->register($parser);
        }
    }

    public function register(ProviderParserInterface $parser): void
    {
        if (!$parser->isEnabled()) {
            return;
        }

        $key = strtolower(trim($parser->getProviderKey()));
        $this->providerKeyMap[$key] = $parser;

        foreach ($parser->getAliases() as $alias) {
            $cleanAlias = strtolower(trim($alias));
            $this->aliasMap[$cleanAlias] = $parser;
        }

        foreach ($parser->getSenderRegexPatterns() as $pattern) {
            $this->regexPatterns[] = [
                'pattern' => $pattern,
                'parser' => $parser,
            ];
        }
    }

    public function warmUp(): void
    {
        $this->isWarmedUp = true;
    }

    public function resolve(NormalizedSmsContext $context): ?ProviderParserInterface
    {
        $orig = $context->originalContext;

        // 1. المطابقة بالتلميح المباشر مفتاح المزود (Provider Key Hint)
        if ($orig->providerKeyHint) {
            $hintKey = strtolower(trim($orig->providerKeyHint));
            if (isset($this->providerKeyMap[$hintKey])) {
                return $this->providerKeyMap[$hintKey];
            }
        }

        // 2. المطابقة بالمرادف المباشر (Exact Alias Match)
        $sender = strtolower(trim($context->normalizedSender));
        if (isset($this->aliasMap[$sender])) {
            return $this->aliasMap[$sender];
        }

        // 3. المطابقة بالتطبيع الصارم (Normalized Alias)
        $cleanSender = strtolower(preg_replace('/[^a-z0-9]/i', '', $sender));
        foreach ($this->aliasMap as $aliasKey => $parser) {
            $cleanAliasKey = strtolower(preg_replace('/[^a-z0-9]/i', '', $aliasKey));
            if ($cleanSender === $cleanAliasKey && !empty($cleanSender)) {
                return $parser;
            }
        }

        // 4. المطابقة بنمط Regex اسم المرسل (Regex Sender Patterns)
        foreach ($this->regexPatterns as $item) {
            if (@preg_match($item['pattern'], $context->normalizedSender) || @preg_match($item['pattern'], $orig->sender)) {
                return $item['parser'];
            }
        }

        return null;
    }

    /** @return array<string, ProviderParserInterface> */
    public function getRegisteredParsers(): array
    {
        return $this->providerKeyMap;
    }
}
