<?php

declare(strict_types=1);

namespace MyFramework\Core\Twig;

use MyFramework\Core\Push\VapidConfig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PushExtension extends AbstractExtension
{
    public function __construct(
        private readonly VapidConfig $vapidConfig,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('my_framework_core_push_vapid_public_key', [$this, 'getVapidPublicKey']),
        ];
    }

    public function getVapidPublicKey(): string
    {
        return $this->vapidConfig->publicKey;
    }
}
