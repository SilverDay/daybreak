<?php

declare(strict_types=1);

namespace Daybreak\Adapter;

use Daybreak\Security\Html;
use Daybreak\Service\FetchClient;
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

    public function fetch(array $source, FetchClient $fetcher): FetchResult
    {
        $res = $fetcher->get(
            (string) $source['feed_url'],
            $source['etag'] ?? null,
            $source['last_modified_hdr'] ?? null
        );

        if ($res['not_modified']) {
            return new FetchResult([], 304, $res['etag'], $res['last_modified'], true);
        }

        $map = $this->decodeFieldMapLenient((string) ($source['field_map'] ?? '{}'));
        $data = json_decode($res['body'], true);
        if (!is_array($data)) {
            return new FetchResult([], $res['status']);
        }

        $rows = $this->rowsFromPayload($data, $map);
        if (!is_array($rows)) {
            return new FetchResult([], $res['status']);
        }

        return new FetchResult(
            $this->rowsToItems($rows, $map),
            $res['status'],
            $res['etag'],
            $res['last_modified']
        );
    }

    /**
     * @return array{result:FetchResult,errors:string[],warnings:string[]}
     */
    public function preview(array $source, FetchClient $fetcher): array
    {
        $res = $fetcher->get(
            (string) $source['feed_url'],
            $source['etag'] ?? null,
            $source['last_modified_hdr'] ?? null
        );

        if ($res['not_modified']) {
            return [
                'result' => new FetchResult([], 304, $res['etag'], $res['last_modified'], true),
                'errors' => [],
                'warnings' => [],
            ];
        }

        $data = json_decode($res['body'], true);
        if (!is_array($data)) {
            return [
                'result' => new FetchResult([], $res['status'], $res['etag'], $res['last_modified']),
                'errors' => ['Preview payload is not valid JSON.'],
                'warnings' => [],
            ];
        }

        [$map, $mapErrors] = $this->decodeFieldMapStrict((string) ($source['field_map'] ?? '{}'));
        if ($mapErrors !== []) {
            return [
                'result' => new FetchResult([], $res['status'], $res['etag'], $res['last_modified']),
                'errors' => $mapErrors,
                'warnings' => [],
            ];
        }

        [$rows, $rowError] = $this->rowsFromPayloadForPreview($data, $map);
        if ($rowError !== null) {
            return [
                'result' => new FetchResult([], $res['status'], $res['etag'], $res['last_modified']),
                'errors' => [$rowError],
                'warnings' => [],
            ];
        }

        $warnings = $this->diagnoseMapAgainstRows($rows, $map);
        $items = $this->rowsToItems($rows, $map);
        if ($items === []) {
            $warnings[] = 'Preview parsed zero items from the current payload.';
        }

        return [
            'result' => new FetchResult($items, $res['status'], $res['etag'], $res['last_modified']),
            'errors' => [],
            'warnings' => $warnings,
        ];
    }

    /** @param array<string,mixed> $map */
    private function rowsFromPayload(array $data, array $map): mixed
    {
        $itemsPath = $map['items_path'] ?? null;
        return $itemsPath !== null ? ($this->dotGet($data, (string) $itemsPath) ?? []) : $data;
    }

    /**
     * @param array<string,mixed> $map
     * @return array{0:array<int,mixed>,1:?string}
     */
    private function rowsFromPayloadForPreview(array $data, array $map): array
    {
        $itemsPath = $map['items_path'] ?? null;
        $rows = $itemsPath !== null ? $this->dotGet($data, (string) $itemsPath) : $data;

        if ($itemsPath !== null && $rows === null) {
            return [[], 'Configured items_path does not exist in payload.'];
        }
        if (!is_array($rows)) {
            return [[], 'Configured items_path does not point to an array of items.'];
        }

        return [$rows, null];
    }

    /**
     * @param array<int,mixed> $rows
     * @param array<string,mixed> $map
     * @return NormalizedItem[]
     */
    private function rowsToItems(array $rows, array $map): array
    {
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
                try {
                    $published = new DateTimeImmutable($dateStr);
                } catch (\Throwable) {
                }
            }

            $items[] = new NormalizedItem(
                guid: $guid !== '' ? $guid : hash('sha256', $url),
                title: $title !== '' ? $title : $url,
                url: $url,
                summary: Html::sanitizeSummary((string) ($rawSum ?? '')) ?: null,
                publishedAt: $published,
            );
        }

        return $items;
    }

    /** @return array<string,mixed> */
    private function decodeFieldMapLenient(string $raw): array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE || array_is_list($decoded)) {
            return [];
        }

        return $decoded;
    }

    /** @return array{0:array<string,mixed>,1:string[]} */
    private function decodeFieldMapStrict(string $raw): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '' || $trimmed === '{}') {
            return [[], []];
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return [[], ['Field map is not valid JSON.']];
        }
        if (array_is_list($decoded)) {
            return [[], ['Field map must be a JSON object with key/value mappings.']];
        }

        return [$decoded, []];
    }

    /**
     * @param array<int,mixed> $rows
     * @param array<string,mixed> $map
     * @return string[]
     */
    private function diagnoseMapAgainstRows(array $rows, array $map): array
    {
        if ($rows === []) {
            return ['Payload contains no rows at the selected items_path.'];
        }

        $warnings = [];
        $keys = ['guid', 'title', 'url', 'summary', 'published_at'];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $map)) {
                continue;
            }

            $path = trim((string) $map[$key]);
            if ($path === '') {
                $warnings[] = "Field map path for {$key} is empty.";
                continue;
            }

            $foundAny = false;
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if ($this->dotGet($row, $path) !== null) {
                    $foundAny = true;
                    break;
                }
            }

            if (!$foundAny) {
                $warnings[] = "Configured path '{$path}' for {$key} was not found in preview rows.";
            }
        }

        $urlPath = (string) ($map['url'] ?? 'url');
        $usableUrls = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $candidate = trim((string) ($this->dotGet($row, $urlPath) ?? ''));
            if ($candidate !== '') {
                $usableUrls++;
            }
        }
        if ($usableUrls === 0) {
            $warnings[] = "No usable URL values were found at path '{$urlPath}'.";
        }

        return $warnings;
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
