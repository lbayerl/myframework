<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Concert;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ConcertImageUploadService
{
    private const TARGET_DIR = 'images/artists/manual';
    private const MAX_SIZE_BYTES = 5242880; // 5 MB

    /**
     * @var array<string, string>
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    public function storeUploadedImage(Concert $concert, UploadedFile $file): string
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Upload fehlgeschlagen. Bitte versuche es erneut.');
        }

        $size = $file->getSize();
        if ($size === false || $size <= 0) {
            throw new \InvalidArgumentException('Die Datei ist leer.');
        }

        if ($size > self::MAX_SIZE_BYTES) {
            throw new \InvalidArgumentException('Das Bild ist zu groß (maximal 5 MB).');
        }

        $mimeType = $file->getMimeType() ?? '';
        $extension = self::ALLOWED_MIME_TYPES[$mimeType] ?? null;
        if ($extension === null) {
            throw new \InvalidArgumentException('Nur JPG, PNG, WEBP oder GIF sind erlaubt.');
        }

        $targetDir = $this->publicDir . '/' . self::TARGET_DIR;
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                throw new \RuntimeException('Upload-Verzeichnis konnte nicht erstellt werden.');
            }
        }

        $filename = $this->buildFileName($concert, $extension);
        $file->move($targetDir, $filename);

        return '/' . self::TARGET_DIR . '/' . $filename;
    }

    private function buildFileName(Concert $concert, string $extension): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $concert->getTitle()) ?? '';
        $slug = trim(strtolower($slug), '-');
        if ($slug === '') {
            $slug = 'concert';
        }

        $slug = substr($slug, 0, 50);
        $idPart = $concert->getId() !== null ? substr($concert->getId(), 0, 8) : 'new';
        $random = bin2hex(random_bytes(4));

        return sprintf('%s-%s-%s.%s', $slug, $idPart, $random, $extension);
    }
}
