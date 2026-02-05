<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Downloads and persists artist images from Wikipedia.
 * Images are stored in public/images/artists/ for direct web access.
 */
final class ArtistImageService
{
    private const IMAGE_DIR = 'images/artists';

    public function __construct(
        private readonly WikipediaClient $wikipediaClient,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {}

    /**
     * Fetches artist image from Wikipedia and saves it locally.
     * Returns the web-accessible path (e.g., "/images/artists/abc123.jpg") or null if no image found.
     *
     * @param string $artistName The artist/band name to search for
     * @param string|null $concertId Optional concert ID for unique filename
     */
    public function fetchAndStoreImage(string $artistName, ?string $concertId = null): ?string
    {
        $artistName = trim($artistName);
        if ($artistName === '') {
            return null;
        }

        try {
            $data = $this->wikipediaClient->enrichArtist($artistName);
            if ($data === null) {
                $this->logger->info('No Wikipedia data found for artist', ['artist' => $artistName]);
                return null;
            }

            // Prefer thumbnail (smaller, faster loading) over original
            $imageUrl = $data['image']['thumbnail'] ?? $data['image']['original'] ?? null;
            if ($imageUrl === null) {
                $this->logger->info('No image found in Wikipedia data', ['artist' => $artistName]);
                return null;
            }

            return $this->downloadAndSave($imageUrl, $artistName, $concertId);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch artist image', [
                'artist' => $artistName,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Downloads image from URL and saves to local filesystem.
     */
    private function downloadAndSave(string $imageUrl, string $artistName, ?string $concertId): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $imageUrl, [
                'headers' => [
                    'User-Agent' => 'MyFriendsConcertsPWA/1.0',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $this->logger->warning('Image download failed', [
                    'url' => $imageUrl,
                    'status' => $statusCode,
                ]);
                return null;
            }

            $content = $response->getContent();
            if ($content === '') {
                return null;
            }

            // Determine file extension from Content-Type or URL
            $extension = $this->guessExtension($response->getHeaders()['content-type'][0] ?? '', $imageUrl);

            // Generate unique filename
            $filename = $this->generateFilename($artistName, $concertId, $extension);

            // Ensure directory exists
            $targetDir = $this->publicDir . '/' . self::IMAGE_DIR;
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            // Save file
            $targetPath = $targetDir . '/' . $filename;
            file_put_contents($targetPath, $content);

            $this->logger->info('Artist image saved', [
                'artist' => $artistName,
                'path' => $filename,
            ]);

            return '/' . self::IMAGE_DIR . '/' . $filename;
        } catch (\Throwable $e) {
            $this->logger->error('Image download error', [
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Deletes an artist image file if it exists.
     */
    public function deleteImage(?string $webPath): void
    {
        if ($webPath === null || $webPath === '') {
            return;
        }

        $filePath = $this->publicDir . $webPath;
        if (is_file($filePath)) {
            unlink($filePath);
            $this->logger->info('Deleted artist image', ['path' => $webPath]);
        }
    }

    private function guessExtension(string $contentType, string $url): string
    {
        // Try from Content-Type first
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
        ];

        foreach ($mimeMap as $mime => $ext) {
            if (str_contains($contentType, $mime)) {
                return $ext;
            }
        }

        // Fallback: try from URL
        $urlPath = parse_url($url, PHP_URL_PATH) ?? '';
        $urlExt = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
        if (in_array($urlExt, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
            return $urlExt === 'jpeg' ? 'jpg' : $urlExt;
        }

        return 'jpg'; // Default fallback
    }

    private function generateFilename(string $artistName, ?string $concertId, string $extension): string
    {
        // Create a slug from artist name
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $artistName);
        $slug = trim(strtolower($slug), '-');
        $slug = substr($slug, 0, 50); // Limit length

        // Add unique suffix (concert ID or hash)
        $suffix = $concertId ? substr($concertId, 0, 8) : substr(md5($artistName . time()), 0, 8);

        return sprintf('%s-%s.%s', $slug, $suffix, $extension);
    }
}
