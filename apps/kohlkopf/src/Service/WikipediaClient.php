<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WikipediaClient
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly CacheInterface $cache,
        private readonly string $base = 'https://de.wikipedia.org',
        private readonly int $cacheTtlSeconds = 604800, // 7 Tage
        private readonly string $userAgent = 'MyFriendsConcertsPWA/1.0 (contact: you@example.com)'
    ) {}

    /**
     * Enrich artist data by searching Wikipedia for the given query.
     * This is the fallback path when MusicBrainz doesn't provide a Wikipedia URL.
     */
    public function enrichArtist(string $query): ?array
    {
        $q = trim($query);
        if ($q === '') {
            return null;
        }

        $cacheKey = 'wiki_artist_' . sha1(mb_strtolower($q));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($q) {
            $item->expiresAfter($this->cacheTtlSeconds);

            $title = $this->findBestTitle($q);
            if ($title === null) {
                return null;
            }

            return $this->fetchSummary($title);
        });
    }

    /**
     * Fetch Wikipedia summary using an exact Wikipedia URL (from MusicBrainz).
     * This avoids the search step entirely, so no disambiguation issues.
     *
     * @param string $wikipediaUrl Full Wikipedia URL, e.g. "https://de.wikipedia.org/wiki/Butterwegge_(Band)"
     */
    public function enrichByUrl(string $wikipediaUrl): ?array
    {
        $wikipediaUrl = trim($wikipediaUrl);
        if ($wikipediaUrl === '') {
            return null;
        }

        $cacheKey = 'wiki_url_' . sha1($wikipediaUrl);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($wikipediaUrl) {
            $item->expiresAfter($this->cacheTtlSeconds);

            // Parse the title from the Wikipedia URL
            // e.g. "https://de.wikipedia.org/wiki/Butterwegge_(Band)" → "Butterwegge_(Band)"
            $parsed = parse_url($wikipediaUrl);
            $path = $parsed['path'] ?? '';
            $host = $parsed['host'] ?? '';

            // Extract title after /wiki/
            if (preg_match('#/wiki/(.+)$#', $path, $m)) {
                $title = rawurldecode($m[1]);
            } else {
                return null;
            }

            // Determine the base URL from the Wikipedia URL itself
            $scheme = $parsed['scheme'] ?? 'https';
            $base = $scheme . '://' . $host;

            return $this->fetchSummaryFromBase($base, $title);
        });
    }

    private function findBestTitle(string $q): ?string
    {
        // Use MediaWiki Action API for search (REST search/title endpoint was removed)
        $url = $this->base . '/w/api.php';
        $resp = $this->http->request('GET', $url, [
            'headers' => [
                'User-Agent' => $this->userAgent,
                'Api-User-Agent' => $this->userAgent,
            ],
            'query' => [
                'action' => 'query',
                'list' => 'search',
                'srsearch' => $q,
                'format' => 'json',
                'srlimit' => 5,
            ],
        ]);

        $data = $resp->toArray(false);

        // MediaWiki Action API returns results in query.search
        $results = $data['query']['search'] ?? [];
        if (!is_array($results) || count($results) === 0) {
            return null;
        }

        // Take the first result (best match)
        $best = $results[0];

        $title = $best['title'] ?? null;
        return is_string($title) && $title !== '' ? $title : null;
    }

    private function fetchSummary(string $title): ?array
    {
        return $this->fetchSummaryFromBase($this->base, $title);
    }

    /**
     * Fetch page summary from any Wikipedia language edition.
     */
    private function fetchSummaryFromBase(string $baseUrl, string $title): ?array
    {
        // Title muss URL-encoded sein
        $url = $baseUrl . '/api/rest_v1/page/summary/' . rawurlencode($title);

        $resp = $this->http->request('GET', $url, [
            'headers' => [
                'User-Agent' => $this->userAgent,
                'Api-User-Agent' => $this->userAgent,
            ],
        ]);

        $s = $resp->toArray(false);

        // Wikipedia Summary kann z.B. type=disambiguation liefern
        // -> trotzdem zurückgeben, aber Frontend kann "Mehrdeutig" anzeigen
        $thumbnail = $s['thumbnail']['source'] ?? null;
        $originalImage = $s['originalimage']['source'] ?? null;

        return [
            'title' => $s['title'] ?? $title,
            'type' => $s['type'] ?? null,
            'description' => $s['description'] ?? null,
            'extract' => $s['extract'] ?? null,
            'extract_html' => $s['extract_html'] ?? null, // wenn du HTML willst
            'image' => [
                'thumbnail' => is_string($thumbnail) ? $thumbnail : null,
                'original' => is_string($originalImage) ? $originalImage : null,
            ],
            'wikipedia_url' => $s['content_urls']['desktop']['page'] ?? null,
        ];
    }
}
