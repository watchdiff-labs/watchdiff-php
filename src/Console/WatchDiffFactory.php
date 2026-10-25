<?php

declare(strict_types=1);

namespace WatchDiff\Core\Console;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use WatchDiff\Core\Contract\StoreInterface;
use WatchDiff\Core\Store\JsonStore;
use WatchDiff\Core\WatchDiff;

final class WatchDiffFactory
{
    public static function create(?StoreInterface $store = null): WatchDiff
    {
        $client         = Psr18ClientDiscovery::find();
        $requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory  = Psr17FactoryDiscovery::findStreamFactory();

        return new WatchDiff($store ?? new JsonStore(), $client, $requestFactory, $streamFactory);
    }
}
