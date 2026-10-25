<?php

declare(strict_types=1);

namespace WatchDiff\Core\Config;

use WatchDiff\Core\Model\DiffReport;

final class AiProvider
{
    /** @param (\Closure(string, DiffReport): string)|null $custom */
    public function __construct(
        public readonly AiProviderType $type,
        public readonly string $apiKey    = '',
        public readonly ?string $model    = null,
        public readonly ?string $baseUrl  = null,
        public readonly ?\Closure $custom = null,
    ) {
    }

    /**
     * Mirrors the npm/Python ports' auto-detection: the first of
     * ANTHROPIC_API_KEY, OPENAI_API_KEY, GEMINI_API_KEY (checked in that
     * order) that is set in the environment picks the provider, with no
     * `aiProvider` needed on WatchConfig. Returns null if none are set.
     */
    public static function fromEnvironment(): ?self
    {
        foreach ([
            'ANTHROPIC_API_KEY' => AiProviderType::Anthropic,
            'OPENAI_API_KEY'    => AiProviderType::OpenAi,
            'GEMINI_API_KEY'    => AiProviderType::Gemini,
        ] as $envVar => $type) {
            $apiKey = getenv($envVar);
            if (false !== $apiKey && '' !== $apiKey) {
                return new self($type, $apiKey);
            }
        }

        return null;
    }
}
