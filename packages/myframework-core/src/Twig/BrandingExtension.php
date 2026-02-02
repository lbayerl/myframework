<?php

declare(strict_types=1);

namespace MyFramework\Core\Twig;

use MyFramework\Core\UI\Branding;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class BrandingExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly Branding $branding,
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'branding' => $this->branding,
        ];
    }
}
