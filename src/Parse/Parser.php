<?php

declare(strict_types=1);

namespace WatchDiff\Core\Parse;

use Symfony\Component\CssSelector\CssSelectorConverter;

final class Parser
{
    public function __construct(
        private readonly CssSelectorConverter $cssConverter = new CssSelectorConverter(),
        private readonly Cleaner $cleaner = new Cleaner(),
    ) {
    }

    /** @param string[] $ignoreSelectors */
    public function extract(string $html, ?string $target, array $ignoreSelectors = []): string
    {
        if (null === $target && [] === $ignoreSelectors) {
            return trim($html);
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_use_internal_errors($previous);

        if ([] !== $ignoreSelectors) {
            $this->cleaner->stripIgnoredSelectors($document, $ignoreSelectors);
        }

        if (null === $target) {
            return trim($document->saveHTML() ?: '');
        }

        $xpath      = new \DOMXPath($document);
        $expression = $this->isXPath($target) ? $target : $this->cssConverter->toXPath($target);

        $nodes = $xpath->query($expression);
        if (false === $nodes || 0 === $nodes->length) {
            return '';
        }

        $parts = [];
        foreach ($nodes as $node) {
            $parts[] = trim($node->textContent);
        }

        return trim(implode("\n", $parts));
    }

    public function isXPath(string $target): bool
    {
        return str_starts_with($target, '/') || str_starts_with($target, './');
    }
}
