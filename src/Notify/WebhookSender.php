<?php

declare(strict_types=1);

namespace WatchDiff\Core\Notify;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use WatchDiff\Core\Fetch\SsrfGuard;
use WatchDiff\Core\Model\DiffReport;
use WatchDiff\Core\Support\Human;

final class WebhookSender
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    /** @param array<string, string> $extraHeaders */
    public function send(string $webhookUrl, DiffReport $report, array $extraHeaders = [], int $retries = 0, bool $allowPrivateUrls = false): void
    {
        SsrfGuard::assertPublicHost($webhookUrl, $allowPrivateUrls);

        $payload = json_encode($this->buildPayload($webhookUrl, $report), JSON_UNESCAPED_SLASHES) ?: '{}';

        $attempt = 0;
        $delayMs = 500;

        while (true) {
            try {
                $request = $this->requestFactory
                    ->createRequest('POST', $webhookUrl)
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody($this->streamFactory->createStream($payload));

                foreach ($extraHeaders as $name => $value) {
                    $request = $request->withHeader($name, $value);
                }

                $this->httpClient->sendRequest($request);

                return;
            } catch (ClientExceptionInterface $exception) {
                if ($attempt >= $retries) {
                    throw $exception;
                }

                usleep($delayMs * 1000);
                $delayMs *= 2;
                ++$attempt;
            }
        }
    }

    /** @return array<string, mixed> */
    private function buildPayload(string $webhookUrl, DiffReport $report): array
    {
        $summary = $this->summary($report);

        if (str_contains($webhookUrl, 'discord.com')) {
            return ['content' => substr($summary, 0, 2000)];
        }

        if (str_contains($webhookUrl, 'hooks.slack.com')) {
            return $this->slackBlockKitPayload($report, $summary);
        }

        if (str_contains($webhookUrl, 'api.telegram.org')) {
            return ['text' => $summary];
        }

        if (str_contains($webhookUrl, 'office.com')) {
            return ['text' => $summary, '@type' => 'MessageCard', '@context' => 'http://schema.org/extensions'];
        }

        if (str_contains($webhookUrl, 'ntfy.sh') || str_contains($webhookUrl, 'ntfy.')) {
            return ['message' => $summary];
        }

        return $report->toArray();
    }

    /** @return array<string, mixed> */
    private function slackBlockKitPayload(DiffReport $report, string $summary): array
    {
        $blocks = [
            [
                'type' => 'header',
                'text' => ['type' => 'plain_text', 'text' => substr($report->label, 0, 150)],
            ],
            [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => substr($summary, 0, 3000)],
            ],
            [
                'type'     => 'context',
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => $report->url],
                ],
            ],
        ];

        return ['text' => substr($summary, 0, 3000), 'blocks' => $blocks];
    }

    private function summary(DiffReport $report): string
    {
        return Human::reportSummary($report);
    }
}
