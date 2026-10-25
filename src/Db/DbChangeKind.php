<?php

declare(strict_types=1);

namespace WatchDiff\Core\Db;

enum DbChangeKind: string
{
    case Inserted          = 'inserted';
    case Deleted           = 'deleted';
    case Updated           = 'updated';
    case SchemaChanged     = 'schema_changed';
    case ThresholdExceeded = 'threshold_exceeded';
    case ValueChanged      = 'value_changed';
}
