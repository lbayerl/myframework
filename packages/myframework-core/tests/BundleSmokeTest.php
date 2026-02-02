<?php

declare(strict_types=1);

namespace MyFramework\Core\Tests;

use MyFramework\Core\MyFrameworkCoreBundle;
use PHPUnit\Framework\TestCase;

final class BundleSmokeTest extends TestCase
{
    public function testBundleClassIsAutoloadable(): void
    {
        self::assertTrue(class_exists(MyFrameworkCoreBundle::class));
    }
}
