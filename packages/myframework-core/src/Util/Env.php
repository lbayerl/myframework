<?php

declare(strict_types=1);

namespace MyFramework\Core\Util;

/**
 * Kleine Helferklasse, damit wir Default-ENV-Ausdrücke zentral und lesbar halten.
 */
final class Env
{
    public const APP_NAME = 'MYFRAMEWORK_APP_NAME';
    public const PRIMARY_COLOR = 'MYFRAMEWORK_PRIMARY_COLOR';
    public const LOGO_PATH = 'MYFRAMEWORK_LOGO_PATH';

    public const VAPID_PUBLIC_KEY = 'VAPID_PUBLIC_KEY';
    public const VAPID_PRIVATE_KEY = 'VAPID_PRIVATE_KEY';
    public const VAPID_SUBJECT = 'VAPID_SUBJECT';

    private function __construct()
    {
    }
}
