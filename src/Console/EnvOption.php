<?php

declare(strict_types=1);

namespace WatchDiff\Core\Console;

use Symfony\Component\Console\Input\InputInterface;

/**
 * Resolves a CLI option against a WATCHDIFF_* environment variable fallback,
 * so every flag can be set once in the environment (CI, systemd unit, Docker
 * image) instead of repeated on every invocation. Precedence is always:
 * explicit CLI flag > environment variable > the option's own default.
 */
final class EnvOption
{
    public static function string(InputInterface $input, string $option, string $envVar, ?string $default = null): ?string
    {
        $value = $input->getOption($option);
        if (null !== $value) {
            return (string) $value;
        }

        $env = getenv($envVar);

        return false !== $env && '' !== $env ? $env : $default;
    }

    /** Same as string(), but a non-null default guarantees a non-null return. */
    public static function stringOr(InputInterface $input, string $option, string $envVar, string $default): string
    {
        return self::string($input, $option, $envVar, $default) ?? $default;
    }

    public static function int(InputInterface $input, string $option, string $envVar, int $default): int
    {
        $value = $input->getOption($option);
        if (null !== $value) {
            return (int) $value;
        }

        $env = getenv($envVar);

        return false !== $env && '' !== $env ? (int) $env : $default;
    }

    public static function float(InputInterface $input, string $option, string $envVar, float $default): float
    {
        $value = $input->getOption($option);
        if (null !== $value) {
            return (float) $value;
        }

        $env = getenv($envVar);

        return false !== $env && '' !== $env ? (float) $env : $default;
    }

    /**
     * For a VALUE_NONE flag: true if the flag was passed, or if the env var
     * is set to anything other than "", "0", or "false" (case-insensitive).
     */
    public static function bool(InputInterface $input, string $option, string $envVar): bool
    {
        if (true === $input->getOption($option)) {
            return true;
        }

        $env = getenv($envVar);

        return false !== $env && !\in_array(strtolower($env), ['', '0', 'false'], true);
    }
}
