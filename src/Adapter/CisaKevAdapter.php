<?php

declare(strict_types=1);

namespace Daybreak\Adapter;

use Daybreak\Security\Html;
use Daybreak\Service\FetchClient;
use DateTimeImmutable;
use DateTimeZone;

/**
 * CISA Known Exploited Vulnerabilities (KEV) adapter.
 * Fetches the most recently added entries from CISA's KEV catalogue — CVEs that
 * are actively exploited in the wild. Replaces the NVD adapter; CISA's servers
 * are stable and require no API key.
 *
 * Feed: https://www.cisa.gov/sites/default/files/feeds/known_exploited_vulnerabilities.json
 */
final class CisaKevAdapter implements SourceAdapter
{
    private const SHOWN = 20;

    public function supports(string $adapterType): bool
    {
        return $adapterType === 'cisa_kev';
    }

    public function fetch(array $source, FetchClient $fetcher): FetchResult
    {
        $url = rtrim((string) $source['feed_url'], '?&');

        $res = $fetcher->get(
            $url,
            $source['etag'] ?? null,
            $source['last_modified_hdr'] ?? null,
            ['Accept: application/json'],
        );

        if ($res['not_modified']) {
            return new FetchResult([], 304, $res['etag'], $res['last_modified']);
        }

        if ($res['status'] !== 200) {
            return new FetchResult([], $res['status']);
        }

        $data = json_decode($res['body'], true);
        if (!is_array($data) || !isset($data['vulnerabilities']) || !is_array($data['vulnerabilities'])) {
            return new FetchResult([], $res['status']);
        }

        // Sort by dateAdded descending so we always show the most recently added entries,
        // regardless of how CISA orders the JSON file.
        $vulns = $data['vulnerabilities'];
        usort($vulns, static function (array $a, array $b): int {
            return strcmp((string) ($b['dateAdded'] ?? ''), (string) ($a['dateAdded'] ?? ''));
        });
        $vulns = array_slice($vulns, 0, self::SHOWN);

        $tz    = new DateTimeZone('UTC');
        $items = [];
        foreach ($vulns as $v) {
            $cveId = (string) ($v['cveID'] ?? '');
            if ($cveId === '') {
                continue;
            }

            $vendor  = (string) ($v['vendorProject'] ?? '');
            $product = (string) ($v['product'] ?? '');
            $name    = (string) ($v['vulnerabilityName'] ?? '');
            $desc    = (string) ($v['shortDescription'] ?? '');
            $action  = (string) ($v['requiredAction'] ?? '');
            $ransomware = (string) ($v['knownRansomwareCampaignUse'] ?? '');

            $titleParts = array_filter([$vendor, $product, $name]);
            $title = $cveId . ($titleParts !== [] ? ' — ' . implode(' ', $titleParts) : '');

            $summary = $desc;
            if ($action !== '' && $action !== 'Unknown') {
                $summary .= ' Required action: ' . $action;
            }
            if (strtolower($ransomware) === 'known') {
                $summary = '[Ransomware] ' . $summary;
            }
            $summary = Html::sanitizeSummary(mb_substr($summary, 0, 400));

            $dateAdded = null;
            if (!empty($v['dateAdded'])) {
                try {
                    $dateAdded = new DateTimeImmutable((string) $v['dateAdded'], $tz);
                } catch (\Throwable) {
                }
            }

            $items[] = new NormalizedItem(
                guid: $cveId,
                title: Html::sanitizeSummary($title),
                url: 'https://nvd.nist.gov/vuln/detail/' . rawurlencode($cveId),
                summary: $summary !== '' ? $summary : null,
                publishedAt: $dateAdded,
            );
        }

        return new FetchResult($items, $res['status'], $res['etag'], $res['last_modified']);
    }
}
