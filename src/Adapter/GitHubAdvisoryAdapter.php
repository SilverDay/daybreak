<?php

declare(strict_types=1);

namespace Daybreak\Adapter;

use Daybreak\Security\Html;
use Daybreak\Service\FetchClient;
use DateTimeImmutable;
use DateTimeZone;

/**
 * GitHub Advisory Database adapter.
 *
 * Fetches the 30 most recently published, GitHub-reviewed security advisories
 * and surfaces them in the main feed. Advisories are rich, narrative-style
 * write-ups (description, affected packages, CVSS, CVE cross-ref) — unlike
 * raw CVE lists from NVD/CISA, they read as articles.
 *
 * Endpoint: https://api.github.com/advisories?type=reviewed&per_page=30&sort=published&direction=desc
 *
 * Auth: anonymous (60 req/hr) or Bearer token from GITHUB_TOKEN env var (5000 req/hr).
 * At a 15-min fetch interval the anonymous rate limit is never exceeded.
 *
 * ETag conditional GET is fully supported — the API returns ETag on every 200.
 */
final class GitHubAdvisoryAdapter implements SourceAdapter
{
    private const PER_PAGE   = 30;
    private const MAX_PKGS   = 3;   // affected packages shown in summary
    private const DESC_CHARS = 300; // characters of description to include in summary

    public function supports(string $adapterType): bool
    {
        return $adapterType === 'github_advisory';
    }

    public function fetch(array $source, FetchClient $fetcher): FetchResult
    {
        $base = rtrim((string) $source['feed_url'], '?&');
        $url  = $base . '?type=reviewed&per_page=' . self::PER_PAGE . '&sort=published&direction=desc';

        $headers = [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ];

        $token = \Daybreak\Config::get('GITHUB_TOKEN');
        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $res = $fetcher->get(
            $url,
            $source['etag']              ?? null,
            $source['last_modified_hdr'] ?? null,
            $headers,
        );

        if ($res['not_modified']) {
            return new FetchResult([], 304, $res['etag'], $res['last_modified']);
        }

        if ($res['status'] !== 200) {
            return new FetchResult([], $res['status']);
        }

        $data = json_decode($res['body'], true);
        if (!is_array($data)) {
            return new FetchResult([], $res['status']);
        }

        $tz    = new DateTimeZone('UTC');
        $items = [];

        foreach ($data as $advisory) {
            if (!is_array($advisory)) {
                continue;
            }

            $ghsaId  = (string) ($advisory['ghsa_id']  ?? '');
            $htmlUrl = (string) ($advisory['html_url']  ?? '');
            if ($ghsaId === '' || $htmlUrl === '') {
                continue;
            }

            $rawSummary  = (string) ($advisory['summary']     ?? '');
            $rawDesc     = (string) ($advisory['description'] ?? '');
            $severity    = strtoupper((string) ($advisory['severity'] ?? 'UNKNOWN'));
            $cveId       = (string) ($advisory['cve_id']  ?? '');

            // Title: "HIGH: Short summary text" — strip any stray HTML/markdown tags
            $cleanSummary = trim(strip_tags($rawSummary));
            if ($cleanSummary === '') {
                // Very rare for reviewed advisories; fall back to GHSA ID
                $cleanSummary = $ghsaId;
            }
            $title = $severity . ': ' . $cleanSummary;

            // Summary: CVE ref + CVSS score + description excerpt + affected packages
            $summaryParts = [];

            if ($cveId !== '') {
                $summaryParts[] = $cveId;
            }

            $cvss = $advisory['cvss'] ?? null;
            if (is_array($cvss) && isset($cvss['score'])) {
                $summaryParts[] = 'CVSS ' . $cvss['score'];
            }

            // Strip markdown headings (## Heading) before stripping HTML so they
            // don't bleed into the stored summary as literal "## " noise.
            $strippedDesc = preg_replace('/^#{1,6}\s+[^\n]*/m', '', $rawDesc) ?? $rawDesc;
            $cleanDesc = trim(strip_tags($strippedDesc));
            if ($cleanDesc !== '') {
                $excerpt = mb_substr($cleanDesc, 0, self::DESC_CHARS);
                if (mb_strlen($cleanDesc) > self::DESC_CHARS) {
                    $excerpt = mb_substr($excerpt, 0, mb_strrpos($excerpt, ' ') ?: self::DESC_CHARS) . '…';
                }
                $summaryParts[] = $excerpt;
            }

            $vulns = $advisory['vulnerabilities'] ?? [];
            if (is_array($vulns) && $vulns !== []) {
                $pkgs = [];
                foreach (array_slice($vulns, 0, self::MAX_PKGS) as $v) {
                    $eco  = (string) ($v['package']['ecosystem'] ?? '');
                    $name = (string) ($v['package']['name']      ?? '');
                    $rng  = (string) ($v['vulnerable_version_range'] ?? '');
                    if ($name !== '') {
                        $pkgs[] = ($eco !== '' ? "{$eco}:{$name}" : $name) . ($rng !== '' ? " {$rng}" : '');
                    }
                }
                if ($pkgs !== []) {
                    $omitted = max(0, count($vulns) - self::MAX_PKGS);
                    $pkgStr  = 'Affects: ' . implode(', ', $pkgs);
                    if ($omitted > 0) {
                        $pkgStr .= " +{$omitted} more";
                    }
                    $summaryParts[] = $pkgStr;
                }
            }

            $summary = Html::sanitizeSummary(implode(' · ', $summaryParts));

            $publishedAt = null;
            if (!empty($advisory['published_at'])) {
                try {
                    $publishedAt = new DateTimeImmutable((string) $advisory['published_at'], $tz);
                } catch (\Throwable) {
                }
            }

            $items[] = new NormalizedItem(
                guid:        $ghsaId,
                title:       Html::sanitizeSummary($title),
                url:         $htmlUrl,
                summary:     $summary !== '' ? $summary : null,
                publishedAt: $publishedAt,
            );
        }

        return new FetchResult($items, $res['status'], $res['etag'], $res['last_modified']);
    }
}
