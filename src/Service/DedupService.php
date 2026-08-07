<?php

declare(strict_types=1);

namespace Daybreak\Service;

use Daybreak\Database;
use PDO;

/**
 * Cross-source dedup for the article feed. Two articles are the same story iff they
 * share a dedup_key (see AggregationService::dedupKey() — a title-token + publish-date
 * fingerprint computed at ingest time). Matching is on dedup_key alone; an earlier
 * version also required the two articles' URLs to be byte-identical, which defeated
 * the whole point since independent outlets never share a URL for the same story.
 *
 * Cross-source duplication is rare in practice, so rather than restructuring a
 * controller's main query around GROUP BY/window functions, findDuplicates() runs two
 * small queries scoped to the caller's existing filter to find which dedup_keys have
 * more than one match and who the non-primary members are. The caller then excludes
 * those ids from its normal paginated query before ORDER BY/LIMIT ever runs, so a
 * story-group can never be split across a page boundary.
 */
final class DedupService
{
    private const MAX_ALSO_BY = 5;

    /**
     * Finds dedup_key groups with more than one member within the caller's filtered
     * scope. $fromJoinSql/$whereSql are the same FROM/JOIN and WHERE fragments (and
     * $params the same bound values, in the same left-to-right order) the caller
     * already builds for its own main query — never raw user input.
     *
     * @param  array<int, int|string> $params
     * @return array{byKey: array<string, array<int, array{id:int, dedup_key:string, source_name:string}>>, excludeIds: int[]}
     */
    public static function findDuplicates(string $fromJoinSql, string $whereSql, array $params): array
    {
        $dupKeys = Database::query(
            "SELECT a.dedup_key
             FROM {$fromJoinSql}
             WHERE {$whereSql}
               AND a.dedup_key IS NOT NULL
             GROUP BY a.dedup_key
             HAVING COUNT(*) > 1
             LIMIT 2000",
            $params
        )->fetchAll(PDO::FETCH_COLUMN);

        if ($dupKeys === []) {
            return ['byKey' => [], 'excludeIds' => []];
        }

        $placeholders = implode(',', array_fill(0, count($dupKeys), '?'));
        $memberRows = Database::query(
            "SELECT a.id, a.dedup_key, s.name AS source_name
             FROM {$fromJoinSql}
             WHERE {$whereSql}
               AND a.dedup_key IN ({$placeholders})
             ORDER BY a.dedup_key, a.published_at DESC, a.id DESC",
            array_merge($params, $dupKeys)
        )->fetchAll();

        return self::resolveGroups($memberRows);
    }

    /**
     * @param  array[] $memberRows Pre-sorted by dedup_key, published_at DESC, id DESC.
     * @return array{byKey: array<string, array[]>, excludeIds: int[]}
     */
    public static function resolveGroups(array $memberRows): array
    {
        $byKey = [];
        foreach ($memberRows as $row) {
            $byKey[(string) $row['dedup_key']][] = $row;
        }

        $excludeIds = [];
        foreach ($byKey as $members) {
            // members[0] is the primary (most-recently published, since the caller
            // sorted the query that way); everything after it in the same group is a
            // non-primary duplicate to exclude from the main paginated query.
            for ($i = 1, $n = count($members); $i < $n; $i++) {
                $excludeIds[] = (int) $members[$i]['id'];
            }
        }

        return ['byKey' => $byKey, 'excludeIds' => $excludeIds];
    }

    /**
     * Attaches also_by / also_by_omitted to each already-fetched primary row.
     *
     * @param  array[] $primaries Must include 'id' and 'dedup_key'.
     * @param  array<string, array[]> $byKey From findDuplicates()/resolveGroups().
     * @return array[] $primaries with also_by (string[]) and also_by_omitted (int) added.
     */
    public static function attachAlsoBy(array $primaries, array $byKey): array
    {
        foreach ($primaries as $i => $primary) {
            $primaries[$i]['also_by'] = [];
            $primaries[$i]['also_by_omitted'] = 0;

            $key = (string) ($primary['dedup_key'] ?? '');
            if ($key === '' || !isset($byKey[$key])) {
                continue;
            }

            foreach ($byKey[$key] as $member) {
                if ((int) $member['id'] === (int) $primary['id']) {
                    continue; // the primary's own row
                }
                $name = (string) $member['source_name'];
                if (in_array($name, $primaries[$i]['also_by'], true)) {
                    continue;
                }
                if (count($primaries[$i]['also_by']) < self::MAX_ALSO_BY) {
                    $primaries[$i]['also_by'][] = $name;
                } else {
                    $primaries[$i]['also_by_omitted']++;
                }
            }
        }

        return $primaries;
    }
}
