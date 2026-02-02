<?php

declare(strict_types=1);

namespace MyFramework\Core\Push;

final class VapidConfig
{
    public function __construct(
        public readonly string $publicKey,
        public readonly string $privateKey,
        public readonly string $subject,
    ) {
    }
}
