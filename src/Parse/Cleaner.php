<?php

declare(strict_types=1);

namespace WatchDiff\Core\Parse;

final class Cleaner
{
    public function clean(string $html): string
    {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html) ?? $html;
        $html = preg_replace('#<!--.*?-->#s', '', $html) ?? $html;

        return $html;
    }

    /** @param string[] $selectors */
    public function stripIgnoredSelectors(\DOMDocument $document, array $selectors): void
    {
        if ([] === $selectors) {
            return;
        }

        $xpath     = new \DOMXPath($document);
        $converter = new \Symfony\Component\CssSelector\CssSelectorConverter();

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($converter->toXPath($selector));
            if (false === $nodes) {
                continue;
            }

            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    /** @param string[] $patterns */
    public function stripIgnorePatterns(string $text, array $patterns): string
    {
        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '', $text) ?? $text;
        }

        return $text;
    }

    public function stripNumbers(string $text): string
    {
        return preg_replace('/\b\d+([.,]\d+)?\b/', '', $text) ?? $text;
    }
}
