<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Concert;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Enriches a Concert with artist data from MusicBrainz + Wikipedia.
 *
 * Flow:
 * 1. MusicBrainz search → MBID, genres, Wikipedia URL
 * 2. Wikipedia fetch (via exact URL from MB, or search fallback) → description, image
 * 3. Download and store image locally
 *
 * All enrichment is best-effort: failures never prevent concert creation.
 */
final class ArtistEnrichmentService
{
    private const IMAGE_DIR = 'images/artists';

    public function __construct(
        private readonly MusicBrainzClient $musicBrainzClient,
        private readonly WikipediaClient $wikipediaClient,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {}

    /**
     * Enrich a concert with artist metadata and image.
     * Modifies the Concert entity directly (caller must flush).
     */
    public function enrich(Concert $concert): void
    {
        $artistName = trim($concert->getTitle());
        if ($artistName === '') {
            return;
        }

        try {
            $wikiData = $this->enrichMetadata($concert, $artistName);
            $this->enrichImage($concert, $artistName, $wikiData);
        } catch (\Throwable $e) {
            $this->logger->error('Artist enrichment failed', [
                'artist' => $artistName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Re-enrich after title change: clears old data and fetches fresh.
     */
    public function reEnrich(Concert $concert): void
    {
        // Delete old image
        $this->deleteImage($concert->getArtistImage());

        // Clear old metadata
        $concert->setMbid(null);
        $concert->setGenres(null);
        $concert->setWikipediaUrl(null);
        $concert->setArtistDescription(null);
        $concert->setArtistImage(null);

        $this->enrich($concert);
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

    private function enrichMetadata(Concert $concert, string $artistName): ?array
    {
        // Step 1: MusicBrainz — get MBID, genres, Wikipedia URL
        $mbData = $this->musicBrainzClient->searchArtist($artistName);

        if ($mbData !== null) {
            $concert->setMbid($mbData['mbid']);

            if ($mbData['genres'] !== []) {
                $concert->setGenres($mbData['genres']);
            }

            if ($mbData['wikipediaUrl'] !== null) {
                $concert->setWikipediaUrl($mbData['wikipediaUrl']);
            }
        }

        // Step 2: Wikipedia — fetch description (and image URL for next step)
        $wikiData = null;
        $wikipediaUrl = $concert->getWikipediaUrl();

        if ($wikipediaUrl !== null) {
            // Optimal path: use exact Wikipedia URL from MusicBrainz
            $wikiData = $this->wikipediaClient->enrichByUrl($wikipediaUrl);
        }

        if ($wikiData === null) {
            // Fallback: search Wikipedia by name (like before)
            $wikiData = $this->wikipediaClient->enrichArtist($artistName);

            // If Wikipedia found something, save its URL
            if ($wikiData !== null && isset($wikiData['wikipedia_url'])) {
                $concert->setWikipediaUrl($wikiData['wikipedia_url']);
            }
        }

        if ($wikiData !== null && isset($wikiData['extract'])) {
            // Truncate to reasonable length for a short description
            $description = $wikiData['extract'];
            if (mb_strlen($description) > 500) {
                $description = mb_substr($description, 0, 497) . '…';
            }
            $concert->setArtistDescription($description);
        }

        return $wikiData;
    }

    private function enrichImage(Concert $concert, string $artistName, ?array $wikiData): void
    {
        if ($wikiData === null) {
            $this->logger->info('No Wikipedia data for image', ['artist' => $artistName]);
            return;
        }

        // Prefer thumbnail (smaller, faster loading) over original
        $imageUrl = $wikiData['image']['thumbnail'] ?? $wikiData['image']['original'] ?? null;
        if ($imageUrl === null) {
            $this->logger->info('No image found in Wikipedia data', ['artist' => $artistName]);
            return;
        }

        $imagePath = $this->downloadAndSave($imageUrl, $artistName, $concert->getId());
        if ($imagePath !== null) {
            $concert->setArtistImage($imagePath);
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
                    'User-Agent' => 'KohlkopfConcertApp/1.0',
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
                if (!mkdir($targetDir, 0755, true)) {
                    $this->logger->error('Failed to create directory', [
                        'dir' => $targetDir,
                        'artist' => $artistName,
                    ]);
                    return null;
                }
            }

            // Save file
            $targetPath = $targetDir . '/' . $filename;
            $bytesWritten = file_put_contents($targetPath, $content);

            if ($bytesWritten === false || $bytesWritten === 0) {
                $this->logger->error('Failed to write image file', [
                    'path' => $targetPath,
                    'artist' => $artistName,
                ]);
                return null;
            }

            $this->logger->info('Artist image saved', [
                'artist' => $artistName,
                'path' => $filename,
                'bytes' => $bytesWritten,
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

    private function guessExtension(string $contentType, string $url): string
    {
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

        $urlPath = parse_url($url, PHP_URL_PATH) ?? '';
        $urlExt = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
        if (in_array($urlExt, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
            return $urlExt === 'jpeg' ? 'jpg' : $urlExt;
        }

        return 'jpg';
    }

    private function generateFilename(string $artistName, ?string $concertId, string $extension): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $artistName);
        $slug = trim(strtolower($slug), '-');
        $slug = substr($slug, 0, 50);

        $suffix = $concertId ? substr($concertId, 0, 8) : substr(md5($artistName . time()), 0, 8);

        return sprintf('%s-%s.%s', $slug, $suffix, $extension);
    }
}
