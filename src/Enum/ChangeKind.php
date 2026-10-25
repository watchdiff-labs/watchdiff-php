<?php

declare(strict_types=1);

namespace WatchDiff\Core\Enum;

enum ChangeKind: string
{
    case Added    = 'added';
    case Removed  = 'removed';
    case Modified = 'modified';
}
