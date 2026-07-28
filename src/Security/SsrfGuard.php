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
    /**
     * @return array{scheme:'http'|'https',host:string,ip:string,port:int}
     */
    public static function assertSafe(string $url): array
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

        $host = self::normalizeHost((string) $parts['host']);
        $ips  = self::resolve($host);
        if ($ips === []) {
            throw new RuntimeException('SSRF: host does not resolve: ' . $host);
        }
        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                throw new RuntimeException('SSRF: host resolves to non-public IP ' . $ip);
            }
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        return [
            'scheme' => $scheme,
            'host'   => $host,
            'ip'     => $ips[0],
            'port'   => $port,
        ];
    }

    /** @return string[] */
    private static function resolve(string $host): array
    {
        $host = self::normalizeHost($host);
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = self::normalizeIp($host);
            return $ip !== null ? [$ip] : [];
        }

        $ips = [];
        foreach (['A' => DNS_A, 'AAAA' => DNS_AAAA] as $records) {
            $r = @dns_get_record($host, $records) ?: [];
            foreach ($r as $rec) {
                if (isset($rec['ip'])) {
                    $ip = self::normalizeIp((string) $rec['ip']);
                    if ($ip !== null) {
                        $ips[] = $ip;
                    }
                }
                if (isset($rec['ipv6'])) {
                    $ip = self::normalizeIp((string) $rec['ipv6']);
                    if ($ip !== null) {
                        $ips[] = $ip;
                    }
                }
            }
        }
        return array_unique($ips);
    }

    private static function isPublicIp(string $ip): bool
    {
        $normalized = self::normalizeIp($ip);
        if ($normalized === null) {
            return false;
        }

        if (filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            foreach (self::ipv4DenyCidrs() as $cidr) {
                if (self::ipInCidr($normalized, $cidr)) {
                    return false;
                }
            }
            return true;
        }

        if (filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            foreach (self::ipv6DenyCidrs() as $cidr) {
                if (self::ipInCidr($normalized, $cidr)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    /** @return list<string> */
    private static function ipv4DenyCidrs(): array
    {
        return [
            '0.0.0.0/8',
            '10.0.0.0/8',
            '100.64.0.0/10',
            '127.0.0.0/8',
            '169.254.0.0/16',
            '172.16.0.0/12',
            '192.0.0.0/24',
            '192.0.2.0/24',
            '192.168.0.0/16',
            '198.18.0.0/15',
            '198.51.100.0/24',
            '203.0.113.0/24',
            '224.0.0.0/4',
            '240.0.0.0/4',
        ];
    }

    /** @return list<string> */
    private static function ipv6DenyCidrs(): array
    {
        return [
            '::/128',
            '::1/128',
            'fc00::/7',
            'fe80::/10',
            '2001:db8::/32',
            'ff00::/8',
            '64:ff9b::/96',
            '2002::/16',
        ];
    }

    private static function normalizeHost(string $host): string
    {
        $host = trim($host);
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            return substr($host, 1, -1);
        }
        return $host;
    }

    private static function normalizeIp(string $ip): ?string
    {
        $ip = self::normalizeHost(trim($ip));
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return null;
        }

        if (strlen($packed) === 16 && self::isIpv4MappedIpv6($packed)) {
            $mapped = substr($packed, 12, 4);
            $mappedText = @inet_ntop($mapped);
            return is_string($mappedText) ? $mappedText : null;
        }

        $normalized = @inet_ntop($packed);
        return is_string($normalized) ? $normalized : null;
    }

    private static function isIpv4MappedIpv6(string $packed): bool
    {
        return substr($packed, 0, 10) === str_repeat("\x00", 10)
            && substr($packed, 10, 2) === "\xff\xff";
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        [$network, $prefix] = explode('/', $cidr, 2);
        $prefixLen = (int) $prefix;

        $ipPacked = @inet_pton($ip);
        $netPacked = @inet_pton($network);

        if ($ipPacked === false || $netPacked === false || strlen($ipPacked) !== strlen($netPacked)) {
            return false;
        }

        $bytes = intdiv($prefixLen, 8);
        $bits = $prefixLen % 8;

        if ($bytes > 0 && substr($ipPacked, 0, $bytes) !== substr($netPacked, 0, $bytes)) {
            return false;
        }

        if ($bits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $bits)) & 0xFF;
        return (
            (ord($ipPacked[$bytes]) & $mask)
            === (ord($netPacked[$bytes]) & $mask)
        );
    }
}
