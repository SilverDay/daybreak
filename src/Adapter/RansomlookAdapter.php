<?php

declare(strict_types=1);

namespace Daybreak\Adapter;

use Daybreak\Service\FetchClient;
use DateTimeImmutable;

/**
 * ransomlook.io recent activity via the public JSON API (no key required).
 * Text-only: group -> victim -> time. Ignores screenshot/magnet fields.
 * Attribution (CC BY 4.0) is rendered by the widget, not stored per-item.
 */
final class RansomlookAdapter implements SourceAdapter
{
    private const ENDPOINT = 'https://www.ransomlook.io/api/recent';
    private const BASE_URL = 'https://www.ransomlook.io';
    private const FALLBACK_URL = 'https://www.ransomlook.io/recent';

    public function supports(string $adapterType): bool
    {
        return $adapterType === 'ransomlook';
    }

    public function fetch(array $source, FetchClient $fetcher): FetchResult
    {
        $url = (string) ($source['feed_url'] ?: self::ENDPOINT);
        $res = $fetcher->get($url);
        $data = json_decode($res['body'], true);
        if (!is_array($data)) {
            return new FetchResult([], $res['status']);
        }

        $items = [];
        foreach ($data as $post) {
            $victim = (string) ($post['post_title'] ?? '');
            $group  = (string) ($post['group_name'] ?? '');
            $when   = (string) ($post['discovered'] ?? '');
            if ($victim === '' && $group === '') {
                continue;
            }
            $published = null;
            if ($when !== '') {
                try {
                    $published = new DateTimeImmutable($when);
                } catch (\Throwable) {
                }
            }
            $link = (string) ($post['link'] ?? $post['url'] ?? $post['post_url'] ?? '');
            $items[] = new NormalizedItem(
                guid: hash('sha256', $group . '|' . $victim . '|' . $when),
                title: trim($group !== '' ? "{$group}: {$victim}" : $victim),
                url: $this->normalizeLink($link),
                summary: null,
                publishedAt: $published,
            );
        }

        return new FetchResult($items, $res['status']);
    }

    private function normalizeLink(string $rawLink): string
    {
        $link = trim($rawLink);
        if ($link === '') {
            return self::FALLBACK_URL;
        }

        if (preg_match('#^https?://#i', $link) === 1) {
            return $link;
        }
        if (str_starts_with($link, '//')) {
            return 'https:' . $link;
        }
        if (str_starts_with($link, '/')) {
            return self::BASE_URL . $link;
        }

        return self::BASE_URL . '/' . ltrim($link, '/');
    }
}
