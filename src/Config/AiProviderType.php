<?php

declare(strict_types=1);

namespace WatchDiff\Core\Config;

enum AiProviderType: string
{
    case Anthropic = 'anthropic';
    case OpenAi    = 'openai';
    case Gemini    = 'gemini';
    case Custom    = 'custom';
}
