<?php

declare(strict_types=1);

namespace MyFramework\Core\UI;

final class Branding
{
    public function __construct(
        public readonly string $appName,
        public readonly string $primaryColor,
        public readonly ?string $logoPath,
    ) {
    }
}
