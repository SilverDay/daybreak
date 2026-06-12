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
    private const MAX_ALSO_BY = 5;

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
                $article['also_by_omitted'] = 0;
                $groups[] = $article;
                continue;
            }

            if (!isset($keyIndex[$key])) {
                $article['also_by'] = [];
                $article['also_by_omitted'] = 0;
                $keyIndex[$key] = count($groups);
                $groups[] = $article;
            } else {
                $idx = $keyIndex[$key];
                if (!self::sameStory($groups[$idx], $article)) {
                    $article['also_by'] = [];
                    $article['also_by_omitted'] = 0;
                    $groups[] = $article;
                    continue;
                }
                // Only add the source name if it differs from the primary's source.
                if ($groups[$idx]['source_name'] !== $article['source_name']) {
                    if (!in_array($article['source_name'], $groups[$idx]['also_by'], true)) {
                        if (count($groups[$idx]['also_by']) < self::MAX_ALSO_BY) {
                            $groups[$idx]['also_by'][] = $article['source_name'];
                        } else {
                            $groups[$idx]['also_by_omitted'] = (int) ($groups[$idx]['also_by_omitted'] ?? 0) + 1;
                        }
                    }
                }
            }
        }

        return $groups;
    }

    private static function sameStory(array $left, array $right): bool
    {
        $leftUrl = self::canonicalUrl((string) ($left['url'] ?? ''));
        $rightUrl = self::canonicalUrl((string) ($right['url'] ?? ''));

        return $leftUrl !== '' && $rightUrl !== '' && $leftUrl === $rightUrl;
    }

    private static function canonicalUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $host = mb_strtolower((string) $parts['host']);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        return $path !== '' ? $host . '/' . $path : $host;
    }
}
