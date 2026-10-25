<?php

declare(strict_types=1);

namespace WatchDiff\Core\Db;

enum DbDiffMode: string
{
    case Row       = 'row';
    case Schema    = 'schema';
    case Aggregate = 'aggregate';
    case Value     = 'value';
}
