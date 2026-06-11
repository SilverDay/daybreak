<?php
declare(strict_types=1);

namespace Daybreak\Adapter;

use Daybreak\Service\FeedFetcher;
use DateTimeImmutable;

/**
 * ransomlook.io recent activity via the public JSON API (no key required).
 * Text-only: group -> victim -> time. Ignores screenshot/magnet fields.
 * Attribution (CC BY 4.0) is rendered by the widget, not stored per-item.
 */
final class RansomlookAdapter implements SourceAdapter
{
    private const ENDPOINT = 'https://www.ransomlook.io/api/recent';

    public function supports(string $adapterType): bool
    {
        return $adapterType === 'ransomlook';
    }

    public function fetch(array $source, FeedFetcher $fetcher): FetchResult
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
                try { $published = new DateTimeImmutable($when); } catch (\Throwable) {}
            }
            $link = (string) ($post['link'] ?? '');
            $items[] = new NormalizedItem(
                guid: hash('sha256', $group . '|' . $victim . '|' . $when),
                title: trim($group !== '' ? "{$group}: {$victim}" : $victim),
                url: $link !== '' ? 'https://www.ransomlook.io' . $link : 'https://www.ransomlook.io/recent',
                summary: null,
                publishedAt: $published,
            );
        }

        return new FetchResult($items, $res['status']);
    }
}
