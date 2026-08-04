<?php
// اختبارات الوحدة الخاصة بمحلل فودافون كاش ومطابقتها مع مكتبة Corpus الحقيقية.

namespace Modules\HwnixCash\Tests\Unit\Parsers;

use Modules\HwnixCash\Domain\Enums\ParserResultStatus;
use Modules\HwnixCash\Domain\Enums\TransactionType;
use Modules\HwnixCash\DTOs\IncomingSmsContext;
use Modules\HwnixCash\Services\Parsers\Normalizers\TextNormalizer;
use Modules\HwnixCash\Services\Parsers\ParserRegistry;
use Modules\HwnixCash\Services\Parsers\PipelineMessageParser;
use Modules\HwnixCash\Services\Parsers\Providers\VfCash\VfCashParser;
use Modules\HwnixCash\Services\Parsers\Stages\RuleBasedParserStage;
use Tests\TestCase;

class VfCashParserTest extends TestCase
{
    private PipelineMessageParser $pipeline;

    protected function setUp(): void
    {
        parent::setUp();

        $vfCashParser = new VfCashParser();
        $registry = new ParserRegistry([$vfCashParser]);
        $normalizer = new TextNormalizer();
        $ruleStage = new RuleBasedParserStage();

        $this->pipeline = new PipelineMessageParser(
            registry: $registry,
            normalizer: $normalizer,
            stages: [$ruleStage]
        );
    }

    public function test_parses_receive_transfer_from_corpus(): void
    {
        $corpusPath = base_path('Modules/HwnixCash/tests/Corpus/Vodafone/Receive/001.txt');
        $this->assertFileExists($corpusPath);

        $body = file_get_contents($corpusPath);
        $context = new IncomingSmsContext(
            body: $body,
            sender: 'VF-Cash'
        );

        $result = $this->pipeline->parse($context);

        $this->assertEquals(ParserResultStatus::SUCCESS, $result->status);
        $this->assertTrue($result->isSupported);
        $this->assertTrue($result->isFinancial);
        $this->assertEquals('receive', $result->transactionType);
        $this->assertEquals(500.00, $result->amount);
        $this->assertEquals('01012345678', $result->targetPhone);
        $this->assertEquals('105234919', $result->transactionId);
        $this->assertEquals(1950.50, $result->availableBalance);
        $this->assertEquals('VF_RECEIVE_001', $result->metadata->patternId);
    }

    public function test_parses_send_transfer_from_corpus(): void
    {
        $corpusPath = base_path('Modules/HwnixCash/tests/Corpus/Vodafone/Send/001.txt');
        $this->assertFileExists($corpusPath);

        $body = file_get_contents($corpusPath);
        $context = new IncomingSmsContext(
            body: $body,
            sender: 'VF-Cash'
        );

        $result = $this->pipeline->parse($context);

        $this->assertEquals(ParserResultStatus::SUCCESS, $result->status);
        $this->assertTrue($result->isSupported);
        $this->assertTrue($result->isFinancial);
        $this->assertEquals('send', $result->transactionType);
        $this->assertEquals(500.00, $result->amount);
        $this->assertEquals('01012345678', $result->targetPhone);
        $this->assertEquals('105234918', $result->transactionId);
        $this->assertEquals(1450.50, $result->availableBalance);
        $this->assertEquals('VF_SEND_001', $result->metadata->patternId);
    }

    public function test_parses_balance_inquiry_from_corpus(): void
    {
        $corpusPath = base_path('Modules/HwnixCash/tests/Corpus/Vodafone/Balance/001.txt');
        $this->assertFileExists($corpusPath);

        $body = file_get_contents($corpusPath);
        $context = new IncomingSmsContext(
            body: $body,
            sender: 'VF-Cash'
        );

        $result = $this->pipeline->parse($context);

        $this->assertEquals(ParserResultStatus::SUCCESS, $result->status);
        $this->assertTrue($result->isSupported);
        $this->assertTrue($result->isFinancial);
        $this->assertEquals('balance', $result->transactionType);
        $this->assertFalse($result->isTransaction);
        $this->assertTrue($result->balanceFound);
        $this->assertEquals(1450.50, $result->availableBalance);
    }

    public function test_parses_promotion_and_terminates_without_ai(): void
    {
        $corpusPath = base_path('Modules/HwnixCash/tests/Corpus/Vodafone/Promotion/001.txt');
        $this->assertFileExists($corpusPath);

        $body = file_get_contents($corpusPath);
        $context = new IncomingSmsContext(
            body: $body,
            sender: 'VF-Cash'
        );

        $result = $this->pipeline->parse($context);

        $this->assertEquals(ParserResultStatus::PROMOTION, $result->status);
        $this->assertTrue($result->isSupported);
        $this->assertFalse($result->isFinancial);
        $this->assertEquals('promotion', $result->messageType);
    }
}
