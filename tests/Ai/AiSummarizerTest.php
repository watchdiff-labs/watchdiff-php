<?php

declare(strict_types=1);

namespace WatchDiff\Core\Tests\Ai;

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use WatchDiff\Core\Ai\AiSummarizer;
use WatchDiff\Core\Config\AiProvider;
use WatchDiff\Core\Config\AiProviderType;
use WatchDiff\Core\Enum\DiffMode;
use WatchDiff\Core\Model\DiffReport;
use WatchDiff\Core\Model\Snapshot;

final class AiSummarizerTest extends TestCase
{
    private function report(): DiffReport
    {
        return new DiffReport(
            url: 'https://example.test',
            label: 'example',
            before: new Snapshot('k', 'a', new \DateTimeImmutable()),
            after: new Snapshot('k', 'b', new \DateTimeImmutable()),
            changes: [],
            diffMode: DiffMode::Line,
            comparedAt: new \DateTimeImmutable(),
        );
    }

    public function testCustomProviderCallsTheGivenClosureInsteadOfAnHttpApi(): void
    {
        $httpClient = new Client();
        $psr17      = new Psr17Factory();
        $summarizer = new AiSummarizer($httpClient, $psr17, $psr17);

        $received = null;
        $provider = new AiProvider(
            type: AiProviderType::Custom,
            custom: function (string $prompt, DiffReport $report) use (&$received) {
                $received = [$prompt, $report];

                return 'custom summary';
            },
        );

        $result = $summarizer->summarize($this->report(), $provider);

        self::assertSame('custom summary', $result);
        self::assertNotNull($received);
        self::assertSame([], $httpClient->getRequests(), 'the custom provider must not go through the HTTP client');
    }

    public function testCustomProviderWithoutAClosureThrows(): void
    {
        $httpClient = new Client();
        $psr17      = new Psr17Factory();
        $summarizer = new AiSummarizer($httpClient, $psr17, $psr17);

        $this->expectException(\InvalidArgumentException::class);
        $summarizer->summarize($this->report(), new AiProvider(type: AiProviderType::Custom));
    }

    public function testOpenAiCompatibleBaseUrlOverridesTheDefaultEndpoint(): void
    {
        $httpClient = new Client();
        $psr17      = new Psr17Factory();
        $httpClient->addResponse($psr17->createResponse(200)->withBody(
            $psr17->createStream(json_encode(['choices' => [['message' => ['content' => 'ok']]]]) ?: '{}'),
        ));

        $summarizer = new AiSummarizer($httpClient, $psr17, $psr17);
        $summarizer->summarize($this->report(), new AiProvider(
            type: AiProviderType::OpenAi,
            apiKey: 'key',
            baseUrl: 'http://localhost:11434/v1',
        ));

        $request = $httpClient->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('http://localhost:11434/v1/chat/completions', (string) $request->getUri());
    }

    public function testFromEnvironmentPicksAnthropicFirst(): void
    {
        putenv('ANTHROPIC_API_KEY=ak');
        putenv('OPENAI_API_KEY=ok');

        try {
            $provider = AiProvider::fromEnvironment();
            self::assertNotNull($provider);
            self::assertSame(AiProviderType::Anthropic, $provider->type);
            self::assertSame('ak', $provider->apiKey);
        } finally {
            putenv('ANTHROPIC_API_KEY');
            putenv('OPENAI_API_KEY');
        }
    }

    public function testFromEnvironmentReturnsNullWhenNothingIsSet(): void
    {
        putenv('ANTHROPIC_API_KEY');
        putenv('OPENAI_API_KEY');
        putenv('GEMINI_API_KEY');

        self::assertNull(AiProvider::fromEnvironment());
    }
}
