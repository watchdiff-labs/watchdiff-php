<?php

declare(strict_types=1);

namespace WatchDiff\Core\Diff;

use WatchDiff\Core\Enum\ChangeKind;
use WatchDiff\Core\Model\Change;

final class SitemapDiffEngine implements DiffEngineInterface
{
    public function diff(string $before, string $after): array
    {
        $beforeUrls = $this->parseUrls($before);
        $afterUrls  = $this->parseUrls($after);

        $changes = [];

        foreach (array_diff($afterUrls, $beforeUrls) as $added) {
            $changes[] = new Change(ChangeKind::Added, after: $added);
        }

        foreach (array_diff($beforeUrls, $afterUrls) as $removed) {
            $changes[] = new Change(ChangeKind::Removed, before: $removed);
        }

        return $changes;
    }

    /** @return string[] */
    private function parseUrls(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        // LIBXML_NONET: defense in depth against XXE-driven network/entity
        // resolution, even though modern libxml2 already disables external
        // entity loading by default.
        $document = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if (false === $document) {
            return [];
        }

        $urls = [];
        foreach ($document->url as $url) {
            $urls[] = (string) $url->loc;
        }

        return $urls;
    }
}
