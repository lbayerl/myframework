<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Searches MusicBrainz for artists and resolves:
 * - MBID (stable ID)
 * - Genre tags
 * - Wikipedia URL (from URL relationships)
 *
 * Rate limit: MusicBrainz requires max 1 request/second and a descriptive User-Agent.
 * We use Symfony Cache (7 days TTL) to minimize requests.
 */
final class MusicBrainzClient
{
    private const BASE_URL = 'https://musicbrainz.org/ws/2';
    private const USER_AGENT = 'KohlkopfConcertApp/1.0 (contact: kohlkopf@example.com)';
    private const CACHE_TTL = 604800; // 7 days

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Search MusicBrainz for an artist and return enrichment data.
     *
     * @return array{mbid: string, name: string, type: string|null, genres: string[], wikipediaUrl: string|null, disambiguation: string|null}|null
     */
    public function searchArtist(string $query): ?array
    {
        $q = trim($query);
        if ($q === '') {
            return null;
        }

        $cacheKey = 'mb_artist_' . sha1(mb_strtolower($q));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($q) {
            $mbid = $this->findBestArtistMbid($q);
            if ($mbid === null) {
                // Short TTL for "not found" — might be a transient API issue
                $item->expiresAfter(3600); // 1 hour
                $this->logger->info('MusicBrainz: no artist found', ['query' => $q]);
                return null;
            }

            // Success: cache for a week
            $item->expiresAfter(self::CACHE_TTL);

            // Respect rate limit before second request
            usleep(1_100_000); // 1.1 seconds

            return $this->fetchArtistDetails($mbid);
        });
    }

    /**
     * Search for artists and return the MBID of the best match.
     * Prefers type=Group (bands) over Person to avoid false matches (e.g. "Butterwegge").
     */
    private function findBestArtistMbid(string $query): ?string
    {
        try {
            $response = $this->http->request('GET', self::BASE_URL . '/artist/', [
                'headers' => ['User-Agent' => self::USER_AGENT, 'Accept' => 'application/json'],
                'query' => [
                    'query' => $query,
                    'fmt' => 'json',
                    'limit' => 5,
                ],
                // Force IPv4 — MusicBrainz IPv6 has TLS issues from some networks
                'extra' => ['curl' => [\CURLOPT_IPRESOLVE => \CURL_IPRESOLVE_V4]],
            ]);

            $data = $response->toArray(false);
            $artists = $data['artists'] ?? [];

            if ($artists === []) {
                return null;
            }

            // Strategy: prefer "Group" type if any top result is a group with a decent score.
            // This handles cases like "Butterwegge" where a professor might rank above the band.
            $bestGroup = null;
            $bestAny = null;

            foreach ($artists as $artist) {
                $score = $artist['score'] ?? 0;
                $type = $artist['type'] ?? null;
                $mbid = $artist['id'] ?? null;

                if ($mbid === null) {
                    continue;
                }

                // Track best overall match
                if ($bestAny === null) {
                    $bestAny = ['mbid' => $mbid, 'score' => $score, 'type' => $type];
                }

                // Track best "Group" match (bands, orchestras, etc.)
                if ($type === 'Group' && $bestGroup === null) {
                    $bestGroup = ['mbid' => $mbid, 'score' => $score, 'type' => $type];
                }
            }

            // Prefer group if its score isn't drastically lower than best overall
            if ($bestGroup !== null && $bestAny !== null) {
                $scoreDiff = $bestAny['score'] - $bestGroup['score'];
                // Use group unless it scores >20 points below the top result
                if ($scoreDiff <= 20) {
                    $this->logger->info('MusicBrainz: chose Group over top result', [
                        'query' => $query,
                        'group_mbid' => $bestGroup['mbid'],
                        'group_score' => $bestGroup['score'],
                        'top_score' => $bestAny['score'],
                        'top_type' => $bestAny['type'],
                    ]);
                    return $bestGroup['mbid'];
                }
            }

            return $bestAny['mbid'] ?? null;
        } catch (\Throwable $e) {
            $this->logger->error('MusicBrainz search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Fetch artist details including URL relationships (for Wikipedia link) and tags (for genres).
     *
     * @return array{mbid: string, name: string, type: string|null, genres: string[], wikipediaUrl: string|null, disambiguation: string|null}|null
     */
    private function fetchArtistDetails(string $mbid): ?array
    {
        try {
            $response = $this->http->request('GET', self::BASE_URL . '/artist/' . $mbid, [
                'headers' => ['User-Agent' => self::USER_AGENT, 'Accept' => 'application/json'],
                'query' => [
                    'inc' => 'url-rels+tags+genres',
                    'fmt' => 'json',
                ],
                'extra' => ['curl' => [\CURLOPT_IPRESOLVE => \CURL_IPRESOLVE_V4]],
            ]);

            $data = $response->toArray(false);

            // Extract Wikipedia URL from relationships
            $wikipediaUrl = $this->extractWikipediaUrl($data['relations'] ?? []);

            // Extract genres — prefer "genres" (official), fallback to "tags"
            $genres = $this->extractGenres($data);

            $result = [
                'mbid' => $mbid,
                'name' => $data['name'] ?? '',
                'type' => $data['type'] ?? null,
                'genres' => $genres,
                'wikipediaUrl' => $wikipediaUrl,
                'disambiguation' => $data['disambiguation'] ?? null,
            ];

            $this->logger->info('MusicBrainz: artist enriched', [
                'mbid' => $mbid,
                'name' => $result['name'],
                'genres' => $genres,
                'hasWikipedia' => $wikipediaUrl !== null,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('MusicBrainz detail fetch failed', [
                'mbid' => $mbid,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract Wikipedia URL from MusicBrainz URL relationships.
     * Prefers German Wikipedia, falls back to English.
     */
    private function extractWikipediaUrl(array $relations): ?string
    {
        $deUrl = null;
        $enUrl = null;
        $anyWikiUrl = null;

        foreach ($relations as $rel) {
            $type = $rel['type'] ?? '';
            $url = $rel['url']['resource'] ?? null;

            if ($type !== 'wikipedia' || !is_string($url)) {
                continue;
            }

            if (str_contains($url, 'de.wikipedia.org')) {
                $deUrl = $url;
            } elseif (str_contains($url, 'en.wikipedia.org')) {
                $enUrl = $url;
            } elseif ($anyWikiUrl === null) {
                $anyWikiUrl = $url;
            }
        }

        return $deUrl ?? $enUrl ?? $anyWikiUrl;
    }

    /**
     * Extract genre names from MusicBrainz response.
     * Prefers official genres, falls back to community tags.
     *
     * @return string[]
     */
    private function extractGenres(array $data): array
    {
        // Official genres (MusicBrainz curated)
        $genres = $data['genres'] ?? [];
        if ($genres !== []) {
            // Sort by count descending, take top 5
            usort($genres, fn(array $a, array $b): int => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));
            return array_slice(
                array_map(fn(array $g): string => $g['name'] ?? '', $genres),
                0,
                5
            );
        }

        // Fallback: community tags
        $tags = $data['tags'] ?? [];
        if ($tags !== []) {
            usort($tags, fn(array $a, array $b): int => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));
            return array_slice(
                array_map(fn(array $t): string => $t['name'] ?? '', $tags),
                0,
                5
            );
        }

        return [];
    }
}
