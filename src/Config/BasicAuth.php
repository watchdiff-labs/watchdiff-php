<?php

declare(strict_types=1);

namespace WatchDiff\Core\Config;

final class BasicAuth
{
    public function __construct(
        public readonly string $user,
        public readonly string $pass,
    ) {
    }

    public function headerValue(): string
    {
        return 'Basic '.base64_encode($this->user.':'.$this->pass);
    }
}
