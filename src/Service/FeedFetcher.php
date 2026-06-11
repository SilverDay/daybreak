<?php
declare(strict_types=1);

namespace Daybreak\Service;

use Daybreak\Config;
use Daybreak\Security\SsrfGuard;
use RuntimeException;

/**
 * The ONLY way to make outbound HTTP requests. SSRF-guarded, with a realistic
 * User-Agent (several sources, e.g. Cloudflare-fronted ones, reject non-browser
 * clients — SPEC Appendix A note 1), conditional GET, timeout and size caps, and
 * manual redirect handling that re-checks SsrfGuard at every hop.
 */
final class FeedFetcher
{
    private const MAX_REDIRECTS = 4;
    private const TIMEOUT_S     = 18;
    private const MAX_BYTES     = 8 * 1024 * 1024; // 8 MB cap

    public function __construct(private readonly string $userAgent = '')
    {
    }

    private function ua(): string
    {
        return $this->userAgent
            ?: (Config::get('FETCH_USER_AGENT')
            ?: 'Mozilla/5.0 (compatible; DaybreakAggregator/0.1; +https://daybreak.silverday.de)');
    }

    /**
     * @return array{status:int,body:string,etag:?string,last_modified:?string,not_modified:bool}
     */
    public function get(string $url, ?string $etag = null, ?string $lastModified = null): array
    {
        $redirects = 0;
        while (true) {
            SsrfGuard::assertSafe($url);

            $ch = curl_init($url);
            $headers = ['Accept: application/rss+xml, application/atom+xml, application/xml, application/json;q=0.9, */*;q=0.5'];
            if ($etag)         { $headers[] = 'If-None-Match: ' . $etag; }
            if ($lastModified) { $headers[] = 'If-Modified-Since: ' . $lastModified; }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => true,
                CURLOPT_FOLLOWLOCATION => false,            // we follow manually to re-check SSRF
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT        => self::TIMEOUT_S,
                CURLOPT_USERAGENT      => $this->ua(),
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_BUFFERSIZE     => 16384,
                CURLOPT_NOPROGRESS     => false,
                CURLOPT_PROGRESSFUNCTION => static function ($ch, $dlTotal, $dlNow) {
                    return $dlNow > self::MAX_BYTES ? 1 : 0; // abort oversized responses
                },
            ]);

            $raw    = curl_exec($ch);
            $errno  = curl_errno($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $hdrLen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            if ($errno !== 0 || $raw === false) {
                throw new RuntimeException('fetch failed: curl errno ' . $errno);
            }

            $rawHeaders = substr($raw, 0, $hdrLen);
            $body       = substr($raw, $hdrLen);

            // Manual redirect handling with SSRF re-check.
            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                if (++$redirects > self::MAX_REDIRECTS) {
                    throw new RuntimeException('too many redirects');
                }
                if (preg_match('/^location:\s*(.+)$/im', $rawHeaders, $m)) {
                    $url   = $this->resolveRedirect($url, trim($m[1]));
                    $etag  = null;
                    $lastModified = null;
                    continue;
                }
                throw new RuntimeException('redirect without Location');
            }

            return [
                'status'       => $status,
                'body'         => $status === 304 ? '' : $body,
                'etag'         => $this->header($rawHeaders, 'etag'),
                'last_modified'=> $this->header($rawHeaders, 'last-modified'),
                'not_modified' => $status === 304,
            ];
        }
    }

    private function resolveRedirect(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $p = parse_url($base);
        $origin = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');
        return str_starts_with($location, '/') ? $origin . $location : $origin . '/' . $location;
    }

    private function header(string $rawHeaders, string $name): ?string
    {
        return preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/im', $rawHeaders, $m)
            ? trim($m[1]) : null;
    }
}
