<?php

declare(strict_types=1);

namespace WatchDiff\Core\Enum;

enum DiffMode: string
{
    case Line    = 'line';
    case Word    = 'word';
    case Json    = 'json';
    case Rss     = 'rss';
    case Sitemap = 'sitemap';
}
