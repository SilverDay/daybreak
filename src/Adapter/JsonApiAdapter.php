<?php
declare(strict_types=1);

namespace Daybreak\Adapter;

use Daybreak\Security\Html;
use Daybreak\Service\FeedFetcher;
use DateTimeImmutable;

/**
 * Generic JSON API adapter. Maps a JSON endpoint to NormalizedItems using a
 * configurable field map stored in sources.field_map (JSON).
 *
 * Supported field_map keys (all optional, dot-notation paths into each item):
 *   items_path   — dot path to the array of items (omit if root is the array)
 *   guid         — unique id field (default: "id")
 *   title        — headline field (default: "title")
 *   url          — link field (default: "url")
 *   summary      — summary field (default: "summary")
 *   published_at — date field (default: "published_at")
 */
final class JsonApiAdapter implements SourceAdapter
{
    public function supports(string $adapterType): bool
    {
        return $adapterType === 'json_api';
    }

    public function fetch(array $source, FeedFetcher $fetcher): FetchResult
    {
        $res = $fetcher->get(
            (string) $source['feed_url'],
            $source['etag'] ?? null,
            $source['last_modified_hdr'] ?? null
        );

        if ($res['not_modified']) {
            return new FetchResult([], 304, $res['etag'], $res['last_modified'], true);
        }

        $map  = json_decode((string) ($source['field_map'] ?? '{}'), true) ?: [];
        $data = json_decode($res['body'], true);
        if (!is_array($data)) {
            return new FetchResult([], $res['status']);
        }

        $itemsPath = $map['items_path'] ?? null;
        $rows      = $itemsPath !== null ? ($this->dotGet($data, (string) $itemsPath) ?? []) : $data;
        if (!is_array($rows)) {
            return new FetchResult([], $res['status']);
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $url = trim((string) ($this->dotGet($row, (string) ($map['url'] ?? 'url')) ?? ''));
            if ($url === '') {
                continue;
            }
            $rawGuid  = $this->dotGet($row, (string) ($map['guid'] ?? 'id'));
            $guid     = $rawGuid !== null ? (string) $rawGuid : hash('sha256', $url);
            $rawTitle = $this->dotGet($row, (string) ($map['title'] ?? 'title'));
            $title    = trim(html_entity_decode(strip_tags((string) ($rawTitle ?? $url)), ENT_QUOTES, 'UTF-8'));
            $rawSum   = $this->dotGet($row, (string) ($map['summary'] ?? 'summary'));
            $dateStr  = (string) ($this->dotGet($row, (string) ($map['published_at'] ?? 'published_at')) ?? '');

            $published = null;
            if ($dateStr !== '') {
                try { $published = new DateTimeImmutable($dateStr); } catch (\Throwable) {}
            }

            $items[] = new NormalizedItem(
                guid:        $guid !== '' ? $guid : hash('sha256', $url),
                title:       $title !== '' ? $title : $url,
                url:         $url,
                summary:     Html::sanitizeSummary((string) ($rawSum ?? '')) ?: null,
                publishedAt: $published,
            );
        }

        return new FetchResult($items, $res['status'], $res['etag'], $res['last_modified']);
    }

    private function dotGet(array $data, string $path): mixed
    {
        $current = $data;
        foreach (explode('.', $path) as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }
        return $current;
    }
}
