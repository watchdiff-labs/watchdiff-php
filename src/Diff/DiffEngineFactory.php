<?php

declare(strict_types=1);

namespace WatchDiff\Core\Diff;

use WatchDiff\Core\Enum\DiffMode;

final class DiffEngineFactory
{
    public function create(DiffMode $mode): DiffEngineInterface
    {
        return match ($mode) {
            DiffMode::Line    => new LineDiffEngine(),
            DiffMode::Word    => new WordDiffEngine(),
            DiffMode::Json    => new JsonDiffEngine(),
            DiffMode::Rss     => new RssDiffEngine(),
            DiffMode::Sitemap => new SitemapDiffEngine(),
        };
    }
}
