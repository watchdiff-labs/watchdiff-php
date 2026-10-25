<?php

declare(strict_types=1);

namespace WatchDiff\Core\Config;

final readonly class BasicAuth
{
    public function __construct(
        public string $user,
        public string $pass,
    ) {
    }

    public function headerValue(): string
    {
        return 'Basic '.base64_encode($this->user.':'.$this->pass);
    }
}
