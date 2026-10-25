<?php

declare(strict_types=1);

namespace WatchDiff\Core\Diff;

use WatchDiff\Core\Model\Change;

interface DiffEngineInterface
{
    /** @return Change[] */
    public function diff(string $before, string $after): array;
}
