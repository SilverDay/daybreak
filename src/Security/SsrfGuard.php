<?php
declare(strict_types=1);

namespace Daybreak\Security;

use RuntimeException;

/**
 * SSRF guard. EVERY outbound fetch of a supplied URL (feed fetcher AND the
 * source-suggestion probe) MUST pass through assertSafe() before connecting,
 * and again after any redirect.
 *
 * Blocks: non-http(s) schemes, credentials in URL, and hosts that resolve to
 * private / reserved / link-local / loopback / cloud-metadata ranges.
 */
final class SsrfGuard
{
    public static function assertSafe(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('SSRF: unparseable URL');
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new RuntimeException('SSRF: scheme not allowed: ' . $scheme);
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('SSRF: credentials in URL not allowed');
        }

        $host = $parts['host'];
        $ips  = self::resolve($host);
        if ($ips === []) {
            throw new RuntimeException('SSRF: host does not resolve: ' . $host);
        }
        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                throw new RuntimeException('SSRF: host resolves to non-public IP ' . $ip);
            }
        }
    }

    /** @return string[] */
    private static function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        $ips = [];
        foreach (['A' => DNS_A, 'AAAA' => DNS_AAAA] as $records) {
            $r = @dns_get_record($host, $records) ?: [];
            foreach ($r as $rec) {
                if (isset($rec['ip']))   { $ips[] = $rec['ip']; }
                if (isset($rec['ipv6'])) { $ips[] = $rec['ipv6']; }
            }
        }
        return array_unique($ips);
    }

    private static function isPublicIp(string $ip): bool
    {
        // Rejects private + reserved ranges (incl. 127/8, 10/8, 172.16/12,
        // 192.168/16, 169.254/16, ::1, fc00::/7, fe80::/10, and the AWS/GCP
        // metadata address 169.254.169.254 which falls in link-local).
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
