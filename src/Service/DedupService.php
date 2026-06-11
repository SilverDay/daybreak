<?php
declare(strict_types=1);

namespace Daybreak\Service;

/**
 * Groups a flat article array by dedup_key, keeping the first occurrence (primary)
 * and collecting all other covering source names into an 'also_by' array.
 * Articles without a dedup_key pass through unchanged with an empty 'also_by'.
 */
final class DedupService
{
    /**
     * @param  array[] $articles  Rows from the articles+sources query; must include 'dedup_key' and 'source_name'.
     * @return array[]            Deduplicated rows; each gains an 'also_by' => string[] key.
     */
    public static function group(array $articles): array
    {
        $groups   = [];
        $keyIndex = []; // dedup_key -> index in $groups

        foreach ($articles as $article) {
            $key = (string) ($article['dedup_key'] ?? '');

            if ($key === '') {
                $article['also_by'] = [];
                $groups[] = $article;
                continue;
            }

            if (!isset($keyIndex[$key])) {
                $article['also_by'] = [];
                $keyIndex[$key] = count($groups);
                $groups[] = $article;
            } else {
                $idx = $keyIndex[$key];
                // Only add the source name if it differs from the primary's source.
                if ($groups[$idx]['source_name'] !== $article['source_name']) {
                    if (!in_array($article['source_name'], $groups[$idx]['also_by'], true)) {
                        $groups[$idx]['also_by'][] = $article['source_name'];
                    }
                }
            }
        }

        return $groups;
    }
}
