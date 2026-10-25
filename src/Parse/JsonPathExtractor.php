<?php

declare(strict_types=1);

namespace WatchDiff\Core\Parse;

final class JsonPathExtractor
{
    /**
     * Extracts a sub-value from a JSON document using a simple dot path
     * (e.g. "$.data.price" or "data.price"). Returns the original content
     * unchanged if it isn't valid JSON or the path doesn't resolve.
     */
    public function extract(string $json, string $path): string
    {
        $decoded = json_decode($json, true);
        if (null === $decoded && 'null' !== trim($json)) {
            return $json;
        }

        $segments = array_values(array_filter(explode('.', ltrim($path, '$.')), static fn (string $s) => '' !== $s));

        $value = $decoded;
        foreach ($segments as $segment) {
            if (!\is_array($value) || !array_key_exists($segment, $value)) {
                return $json;
            }

            $value = $value[$segment];
        }

        return \is_string($value) ? $value : (json_encode($value) ?: $json);
    }
}
