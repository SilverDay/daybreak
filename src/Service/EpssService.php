<?php

declare(strict_types=1);

namespace Daybreak\Service;

use Daybreak\Database;

/**
 * Fetches EPSS (Exploit Prediction Scoring System) scores from api.first.org
 * and stores them in the cve_epss table.
 *
 * EPSS scores are refreshed weekly — they update daily on FIRST's end but
 * the widget doesn't need sub-daily freshness.
 */
final class EpssService
{
    private const BATCH_SIZE    = 5;
    private const REQUEST_DELAY = 600_000; // µs between requests to respect rate limit
    private const STALE_DAYS = 7;
    private const API_URL    = 'https://api.first.org/data/v1/epss';

    public function __construct(private readonly FetchClient $fetcher) {}

    /**
     * Finds CVE IDs from articles that have no score or a stale score,
     * fetches them from the FIRST EPSS API in one batched request, and upserts.
     * Silently returns on any error to never interrupt the cron run.
     */
    public function refreshDue(): void
    {
        $cveIds = $this->findDue();
        if ($cveIds === []) {
            return;
        }
        $this->fetchAndStore($cveIds);
    }

    /**
     * @return string[]
     */
    private function findDue(): array
    {
        $rows = Database::query(
            "SELECT DISTINCT a.guid AS cve_id
             FROM articles a
             JOIN sources s ON s.id = a.source_id AND s.adapter_type IN ('cisa_kev', 'nvd')
             LEFT JOIN cve_epss e ON e.cve_id = a.guid
             WHERE a.guid REGEXP ?
               AND (e.cve_id IS NULL OR e.fetched_at < DATE_SUB(NOW(), INTERVAL ? DAY))
             LIMIT ?",
            ['^CVE-[0-9]{4}-[0-9]+', self::STALE_DAYS, self::BATCH_SIZE]
        )->fetchAll(\PDO::FETCH_COLUMN);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Fetches EPSS scores for the given CVE IDs one at a time.
     * The FIRST EPSS API v1 does not support multi-CVE batch queries;
     * individual requests are required.
     *
     * @param string[] $cveIds
     */
    private function fetchAndStore(array $cveIds): void
    {
        foreach ($cveIds as $i => $cveId) {
            if ($i > 0) {
                usleep(self::REQUEST_DELAY);
            }
            $url = self::API_URL . '?cve=' . rawurlencode($cveId);
            try {
                $res = $this->fetcher->get($url, null, null, ['Accept: application/json']);
            } catch (\Throwable) {
                continue;
            }
            if ($res['status'] !== 200) {
                continue;
            }
            $items = $this->parseBody($res['body']);
            foreach ($items as $row) {
                Database::query(
                    'INSERT INTO cve_epss (cve_id, epss_score, percentile, fetched_at)
                     VALUES (?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE epss_score = VALUES(epss_score),
                                              percentile = VALUES(percentile),
                                              fetched_at = NOW()',
                    [$row['cve_id'], $row['epss_score'], $row['percentile']]
                );
            }
        }
    }

    /**
     * Parses the FIRST EPSS API JSON response.
     * Returns an array of ['cve_id' => string, 'epss_score' => float, 'percentile' => float].
     *
     * @return array<array{cve_id: string, epss_score: float, percentile: float}>
     */
    private function parseBody(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
            return [];
        }

        $out = [];
        foreach ($data['data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cveId = (string) ($row['cve'] ?? '');
            $epss  = (string) ($row['epss'] ?? '');
            $pct   = (string) ($row['percentile'] ?? '0');
            if ($cveId === '' || $epss === '') {
                continue;
            }
            $out[] = [
                'cve_id'     => $cveId,
                'epss_score' => (float) $epss,
                'percentile' => (float) $pct,
            ];
        }
        return $out;
    }
}
