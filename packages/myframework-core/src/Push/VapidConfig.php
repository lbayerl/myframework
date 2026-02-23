<?php

declare(strict_types=1);

namespace MyFramework\Core\Push;

final class VapidConfig
{
    public readonly string $subject;

    public function __construct(
        public readonly string $publicKey,
        public readonly string $privateKey,
        string $subject,
    ) {
        // RFC 8292 requires subject to be a mailto: or https: URL.
        // Apple Web Push strictly validates this — bare emails cause BadJwtToken.
        if ($subject !== '' && !str_starts_with($subject, 'mailto:') && !str_starts_with($subject, 'https://')) {
            $subject = 'mailto:' . $subject;
        }

        $this->subject = $subject;
    }
}
