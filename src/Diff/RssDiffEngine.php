<?php

declare(strict_types=1);

namespace WatchDiff\Core\Diff;

use WatchDiff\Core\Enum\ChangeKind;
use WatchDiff\Core\Model\Change;

final class RssDiffEngine implements DiffEngineInterface
{
    public function __construct(private readonly LineDiffEngine $fallback = new LineDiffEngine())
    {
    }

    public function diff(string $before, string $after): array
    {
        $beforeItems = $this->parseItems($before);
        $afterItems  = $this->parseItems($after);

        if (null === $beforeItems || null === $afterItems) {
            return $this->fallback->diff($before, $after);
        }

        $changes = [];

        foreach ($afterItems as $id => $item) {
            if (!isset($beforeItems[$id])) {
                $changes[] = new Change(ChangeKind::Added, after: $item['title'], context: $item['link']);
                continue;
            }

            if ($beforeItems[$id]['title'] !== $item['title'] || $beforeItems[$id]['link'] !== $item['link']) {
                $changes[] = new Change(ChangeKind::Modified, before: $beforeItems[$id]['title'], after: $item['title'], context: $item['link']);
            }
        }

        foreach ($beforeItems as $id => $item) {
            if (!isset($afterItems[$id])) {
                $changes[] = new Change(ChangeKind::Removed, before: $item['title'], context: $item['link']);
            }
        }

        return $changes;
    }

    /** @return array<string, array{title: string, link: string}>|null */
    private function parseItems(string $xml): ?array
    {
        $previous = libxml_use_internal_errors(true);
        // LIBXML_NONET: defense in depth against XXE-driven network/entity
        // resolution, even though modern libxml2 already disables external
        // entity loading by default.
        $document = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if (false === $document) {
            return null;
        }

        $items = [];

        if (isset($document->channel->item)) {
            foreach ($document->channel->item as $item) {
                $id         = (string) ($item->guid ?? $item->link ?? $item->title);
                $items[$id] = [
                    'title' => (string) ($item->title ?? ''),
                    'link'  => (string) ($item->link ?? ''),
                ];
            }

            return $items;
        }

        $namespaces = $document->getNamespaces(true);
        if (isset($namespaces['']) && str_contains($namespaces[''], 'Atom') || 'feed' === $document->getName()) {
            foreach ($document->entry as $entry) {
                $id   = (string) ($entry->id ?? $entry->title);
                $link = '';
                foreach ($entry->link as $link_) {
                    $link = (string) ($link_['href'] ?? '');
                    break;
                }
                $items[$id] = [
                    'title' => (string) ($entry->title ?? ''),
                    'link'  => $link,
                ];
            }

            return $items;
        }

        return null;
    }
}
