<?php

declare(strict_types=1);

namespace WatchDiff\Core\Ai;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use WatchDiff\Core\Config\AiProvider;
use WatchDiff\Core\Config\AiProviderType;
use WatchDiff\Core\Fetch\BoundedBodyReader;
use WatchDiff\Core\Model\DiffReport;

/**
 * Generates a natural-language summary of a DiffReport by calling the chosen
 * provider's HTTP API directly (no vendor SDK) through the same PSR-18 client
 * the rest of watchdiff-core uses.
 */
final class AiSummarizer
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function summarize(DiffReport $report, AiProvider $provider, string|\Closure|null $prompt = null): string
    {
        $promptText = $prompt instanceof \Closure ? $prompt($report) : ($prompt ?? $this->defaultPrompt($report));

        return match ($provider->type) {
            AiProviderType::Anthropic => $this->callAnthropic($provider, $promptText),
            AiProviderType::OpenAi    => $this->callOpenAi($provider, $promptText),
            AiProviderType::Gemini    => $this->callGemini($provider, $promptText),
            AiProviderType::Custom    => $this->callCustom($provider, $promptText, $report),
        };
    }

    private function defaultPrompt(DiffReport $report): string
    {
        $lines = ["Summarize the following changes detected on {$report->url} in one or two sentences:"];
        foreach ($report->changes as $change) {
            $lines[] = sprintf('- [%s] %s -> %s', $change->kind->value, $change->before ?? '-', $change->after ?? '-');
        }

        return implode("\n", $lines);
    }

    private function callAnthropic(AiProvider $provider, string $prompt): string
    {
        $body = [
            'model'      => $provider->model ?? 'claude-haiku-4-5-20251001',
            'max_tokens' => 300,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ];

        $response = $this->post(
            'https://api.anthropic.com/v1/messages',
            $body,
            ['x-api-key' => $provider->apiKey, 'anthropic-version' => '2023-06-01'],
        );

        return (string) ($response['content'][0]['text'] ?? '');
    }

    /**
     * `baseUrl` lets this same code path reach any OpenAI-compatible chat
     * completions endpoint (Ollama, Mistral, Groq, Together AI, ...) by
     * pointing it elsewhere - those providers all speak this request/response
     * shape, so no separate branch is needed per provider.
     */
    private function callOpenAi(AiProvider $provider, string $prompt): string
    {
        $body = [
            'model'    => $provider->model ?? 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];

        $response = $this->post(
            rtrim($provider->baseUrl ?? 'https://api.openai.com/v1', '/').'/chat/completions',
            $body,
            ['Authorization' => 'Bearer '.$provider->apiKey],
        );

        return (string) ($response['choices'][0]['message']['content'] ?? '');
    }

    private function callCustom(AiProvider $provider, string $prompt, DiffReport $report): string
    {
        if (null === $provider->custom) {
            throw new \InvalidArgumentException('AiProviderType::Custom requires a "custom" closure on AiProvider.');
        }

        return ($provider->custom)($prompt, $report);
    }

    private function callGemini(AiProvider $provider, string $prompt): string
    {
        $model = $provider->model ?? 'gemini-2.0-flash';
        $body  = ['contents' => [['parts' => [['text' => $prompt]]]]];

        $response = $this->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$provider->apiKey}",
            $body,
            [],
        );

        return (string) ($response['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     */
    private function post(string $url, array $body, array $headers): array
    {
        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode($body) ?: '{}'));

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $response = $this->httpClient->sendRequest($request);

        return (array) json_decode(BoundedBodyReader::read($response->getBody()), true);
    }
}
