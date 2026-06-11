<?php
declare(strict_types=1);

namespace Daybreak\Adapter;

use Daybreak\Security\Html;
use Daybreak\Service\FeedFetcher;
use DateTimeImmutable;

/** Parses RSS 2.0 and Atom. The workhorse adapter for most sources. */
final class RssAtomAdapter implements SourceAdapter
{
    public function supports(string $adapterType): bool
    {
        return $adapterType === 'rss_atom';
    }

    public function fetch(array $source, FeedFetcher $fetcher): FetchResult
    {
        $res = $fetcher->get((string) $source['feed_url'], $source['etag'] ?? null, $source['last_modified_hdr'] ?? null);
        if ($res['not_modified']) {
            return new FetchResult([], 304, $res['etag'], $res['last_modified'], true);
        }

        $xml = @simplexml_load_string($res['body'], 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
        if ($xml === false) {
            return new FetchResult([], $res['status'], $res['etag'], $res['last_modified']);
        }

        $items = [];
        if (isset($xml->channel->item)) {                 // RSS 2.0
            foreach ($xml->channel->item as $it) {
                $items[] = $this->item(
                    (string) ($it->guid ?: $it->link),
                    (string) $it->title,
                    (string) $it->link,
                    (string) ($it->description ?? ''),
                    (string) ($it->pubDate ?? '')
                );
            }
        } elseif (isset($xml->entry)) {                   // Atom
            foreach ($xml->entry as $en) {
                $link = '';
                foreach ($en->link as $l) {
                    if ((string) $l['rel'] === 'alternate' || (string) $l['rel'] === '') {
                        $link = (string) $l['href'];
                        break;
                    }
                }
                $items[] = $this->item(
                    (string) ($en->id ?: $link),
                    (string) $en->title,
                    $link,
                    (string) ($en->summary ?: $en->content ?? ''),
                    (string) ($en->updated ?: $en->published ?? '')
                );
            }
        }

        return new FetchResult($items, $res['status'], $res['etag'], $res['last_modified']);
    }

    private function item(string $guid, string $title, string $url, string $summary, string $date): NormalizedItem
    {
        $published = null;
        if ($date !== '') {
            try { $published = new DateTimeImmutable($date); } catch (\Throwable) {}
        }
        return new NormalizedItem(
            guid: $guid !== '' ? $guid : hash('sha256', $url),
            title: trim(html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8')),
            url: trim($url),
            summary: Html::sanitizeSummary($summary) ?: null,
            publishedAt: $published,
        );
    }
}
