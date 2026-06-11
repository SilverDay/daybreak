<?php
declare(strict_types=1);

namespace Daybreak\Adapter;

use Daybreak\Service\FeedFetcher;
use DateTimeImmutable;
use DateTimeZone;

/**
 * NIST NVD CVE API 2.0 adapter.
 * Fetches recently published CVEs (last 7 days, up to 20) for the CVE widget.
 * Items are stored in the articles table but the PublicController surfaces them
 * in the widget rail, not the main feed (adapter_type = 'nvd' is excluded from feed).
 */
final class NvdAdapter implements SourceAdapter
{
    public function supports(string $adapterType): bool
    {
        return $adapterType === 'nvd';
    }

    public function fetch(array $source, FeedFetcher $fetcher): FetchResult
    {
        $base  = rtrim((string) $source['feed_url'], '?&');
        $tz    = new DateTimeZone('UTC');
        $start = (new DateTimeImmutable('-7 days', $tz))->format('Y-m-d\TH:i:s.000');
        $end   = (new DateTimeImmutable('now',     $tz))->format('Y-m-d\TH:i:s.000');
        $url   = $base
            . '?pubStartDate=' . urlencode($start)
            . '&pubEndDate='   . urlencode($end)
            . '&resultsPerPage=20';

        $res  = $fetcher->get($url);
        $data = json_decode($res['body'], true);

        if (!is_array($data) || !isset($data['vulnerabilities']) || !is_array($data['vulnerabilities'])) {
            return new FetchResult([], $res['status']);
        }

        $items = [];
        foreach ($data['vulnerabilities'] as $vuln) {
            $cve = $vuln['cve'] ?? null;
            if (!is_array($cve) || !isset($cve['id'])) {
                continue;
            }

            $id = (string) $cve['id'];

            // English description only.
            $desc = '';
            foreach ($cve['descriptions'] ?? [] as $d) {
                if (($d['lang'] ?? '') === 'en') {
                    $desc = (string) ($d['value'] ?? '');
                    break;
                }
            }

            // CVSS severity: prefer v3.1, then v3.0, then v2.
            $severity = null;
            $score    = null;
            foreach (['cvssMetricV31', 'cvssMetricV30'] as $key) {
                if (isset($cve['metrics'][$key][0]['cvssData'])) {
                    $m        = $cve['metrics'][$key][0]['cvssData'];
                    $severity = (string) ($m['baseSeverity'] ?? '');
                    $score    = $m['baseScore'] ?? null;
                    break;
                }
            }
            if ($severity === null || $severity === '') {
                if (isset($cve['metrics']['cvssMetricV2'][0]['cvssData'])) {
                    $m        = $cve['metrics']['cvssMetricV2'][0]['cvssData'];
                    $severity = (string) ($m['baseSeverity'] ?? '');
                    $score    = $m['baseScore'] ?? null;
                }
            }

            $summary = '';
            if ($severity !== '' && $severity !== null) {
                $summary = $severity . ($score !== null ? ' (' . $score . ')' : '');
                if ($desc !== '') {
                    $summary .= ' — ' . $desc;
                }
            } else {
                $summary = $desc;
            }
            if (mb_strlen($summary) > 400) {
                $summary = mb_substr($summary, 0, 399) . '…';
            }

            $published = null;
            if (!empty($cve['published'])) {
                try {
                    $published = new DateTimeImmutable((string) $cve['published'], new DateTimeZone('UTC'));
                } catch (\Throwable) {}
            }

            $items[] = new NormalizedItem(
                guid:        $id,
                title:       $id,
                url:         'https://nvd.nist.gov/vuln/detail/' . rawurlencode($id),
                summary:     $summary !== '' ? $summary : null,
                publishedAt: $published,
            );
        }

        return new FetchResult($items, $res['status'], $res['etag'], $res['last_modified']);
    }
}
