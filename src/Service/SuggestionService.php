<?php
declare(strict_types=1);

namespace Daybreak\Service;

use Daybreak\Database;
use Daybreak\Security\SsrfGuard;

/**
 * Source-suggestion submission and SSRF-guarded feed auto-probe.
 * The probe is best-effort: it fails silently if the URL is unreachable,
 * blocked by SsrfGuard, or not a parseable feed.
 */
final class SuggestionService
{
    /**
     * Probe a URL for a parseable RSS/Atom feed.
     * Returns ['adapter_type' => 'rss_atom', 'feed_url' => $url] on success, null otherwise.
     * Never throws — probe failures are non-fatal.
     */
    public static function probe(string $url): ?array
    {
        try {
            SsrfGuard::assertSafe($url);
            $resp = (new FeedFetcher())->get($url);
            if ($resp['status'] !== 200 || $resp['body'] === '') {
                return null;
            }
            $body = $resp['body'];

            // Direct feed: look for RSS/Atom root elements.
            if (preg_match('#<(rss|feed|channel)\b#i', substr($body, 0, 2000))) {
                libxml_use_internal_errors(true);
                $xml = @simplexml_load_string($body);
                libxml_clear_errors();
                if ($xml !== false) {
                    return ['adapter_type' => 'rss_atom', 'feed_url' => $url];
                }
            }

            // HTML page: look for feed autodiscovery <link> tags.
            $pattern = '#<link\b[^>]*\btype=["\']application/(rss|atom)\+xml["\'][^>]*>#i';
            if (preg_match_all($pattern, substr($body, 0, 20000), $hits)) {
                foreach ($hits[0] as $tag) {
                    if (preg_match('#\bhref=["\']([^"\']+)["\']#i', $tag, $hm)) {
                        $feedUrl = self::resolveUrl($url, html_entity_decode($hm[1], ENT_QUOTES));
                        return ['adapter_type' => 'rss_atom', 'feed_url' => $feedUrl];
                    }
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Store a suggestion. Returns the new suggestion ID.
     * $probe should be the result of self::probe() or null.
     */
    public static function submit(
        ?int   $userId,
        string $name,
        string $homepageUrl,
        ?string $feedUrl,
        ?string $note,
        ?array  $probe
    ): int {
        Database::query(
            'INSERT INTO source_suggestions
             (suggested_by, name, homepage_url, feed_url, detected_adapter, note)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $userId,
                mb_substr(trim($name), 0, 120),
                mb_substr(trim($homepageUrl), 0, 500),
                $feedUrl !== null ? mb_substr(trim($feedUrl), 0, 500) : null,
                $probe['adapter_type'] ?? null,
                $note !== null ? mb_substr(trim($note), 0, 500) : null,
            ]
        );
        return (int) Database::lastInsertId();
    }

    private static function resolveUrl(string $base, string $href): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $p = parse_url($base);
        $origin = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');
        return str_starts_with($href, '/') ? $origin . $href : $origin . '/' . ltrim($href, '/');
    }
}
