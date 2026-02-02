<?php

declare(strict_types=1);

namespace MyFramework\Core\Security\Service;

final class TokenFactory
{
    /**
     * @return array{plain: string, hash: string}
     */
    public function createTokenPair(): array
    {
        $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash = hash('sha256', $plain);

        return ['plain' => $plain, 'hash' => $hash];
    }
}
